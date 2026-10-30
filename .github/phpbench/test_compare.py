import json
from pathlib import Path
import re
import subprocess
import tempfile
import unittest
import xml.etree.ElementTree as ET

import compare


GUARDED = """public static function readArray(string $file): ?array
{
    if (!self::isSafeToRead($file)) {
        return null;
    }
    return require $file;
}
public static function isSafeToRead(string $file): bool
{
    return is_file($file);
}
"""


class ComparisonPlanTest(unittest.TestCase):
    def test_allowance_only_when_guard_is_introduced_and_used(self):
        self.assertTrue(compare.introduces_guard("old source", GUARDED))
        for base, head in [
            (GUARDED, GUARDED),
            (GUARDED, "old source"),
            ("old source", "old source"),
            ("old source", GUARDED.replace("!self::isSafeToRead($file)", "false")),
            ("old source", "/*" + GUARDED + "*/"),
        ]:
            with self.subTest(base=base, head=head):
                self.assertFalse(compare.introduces_guard(base, head))

    def test_exactly_four_subjects_get_40_percent(self):
        core, startup = compare.selections(True)
        self.assertEqual(10, core[3])
        self.assertEqual(40, startup[3])
        for name in compare.STARTUP_SUBJECTS:
            self.assertRegex(name, startup[2])
            self.assertNotRegex(name, core[2])
        for name in [
            r"\GacelaTest\Benchmark\Config\ConfigInitBench::bench_config_init_cold",
            r"\GacelaTest\Benchmark\Bootstrap\BootstrapBench::bench_bootstrap_cold_rescan",
            compare.STARTUP_SUBJECTS[0] + "_extra",
            "Other" + compare.STARTUP_SUBJECTS[0],
        ]:
            self.assertRegex(name, core[2])
            self.assertNotRegex(name, startup[2])

    def test_strict_after_transition(self):
        self.assertEqual([("base_gate", "gate", None, 10)], compare.selections(False))

    def test_base_and_head_use_same_selections_and_order(self):
        for transition in (False, True):
            plan = {"transition": transition}
            base = list(compare.commands(plan, "base"))[:-1]
            head = list(compare.commands(plan, "gate"))
            selection = lambda args: [arg for arg in args if arg.startswith(("--group=", "--filter="))]
            self.assertEqual(list(map(selection, base)), list(map(selection, head)))
            for baseline, candidate in zip(base, head):
                tag = next(arg.removeprefix("--tag=") for arg in baseline if arg.startswith("--tag="))
                self.assertIn("--ref=" + tag, candidate)
            self.assertIn("--group=informational", list(compare.commands(plan, "info"))[0])

    def test_invalid_or_missing_refs_fail_closed(self):
        for ref in ("", "main", "-" * 40, "0" * 40):
            with self.subTest(ref=ref), self.assertRaises((ValueError, subprocess.CalledProcessError)):
                compare.source_at(ref)


class NativePhpBenchTest(unittest.TestCase):
    """PhpBench's debug executor selects real subjects without measuring them."""

    def run_selection(self, directory, name, arguments, time=100):
        output = Path(directory) / (name + ".xml")
        config = json.loads(Path("phpbench.json").read_text())
        config["runner.bootstrap"] = str(Path("vendor/autoload.php").resolve())
        config["runner.path"] = str(Path("tests/Benchmark").resolve())
        config["storage.xml_storage_path"] = str(Path(directory) / "storage")
        profile = Path(directory) / "phpbench.json"
        profile.write_text(json.dumps(config))
        command = [
            "vendor/bin/phpbench", "run", "--config=" + str(profile), "--group=gate", "--progress=plain",
            "--iterations=1", "--revs=1", "--warmup=1",
            "--executor=" + json.dumps({"executor": "debug", "times": [time]}),
            "--dump-file=" + str(output),
        ] + arguments
        result = subprocess.run(command, text=True, capture_output=True)
        return result, output

    def subjects(self, output):
        root = ET.parse(output).getroot()
        return {
            benchmark.attrib["class"] + "::" + subject.attrib["name"]
            for benchmark in root.iter("benchmark")
            for subject in benchmark.findall("subject")
        }

    def test_a_benchmark_exception_keeps_its_subject_message_and_failure_status(self):
        with tempfile.TemporaryDirectory() as directory:
            fixture = Path(directory) / "DiagnosticFailureBench.php"
            fixture.write_text("""<?php
use PhpBench\\Attributes as Bench;
#[Bench\\Groups(['gate'])]
final class DiagnosticFailureBench
{
    public function benchThrows(): void
    {
        throw new RuntimeException('known benchmark exception');
    }
}
""")
            config = json.loads(Path("phpbench.json").read_text())
            config["runner.bootstrap"] = str(Path("vendor/autoload.php").resolve())
            config["runner.path"] = str(fixture)
            config["storage.xml_storage_path"] = str(Path(directory) / "storage")
            profile = Path(directory) / "phpbench.json"
            profile.write_text(json.dumps(config))
            command = next(compare.commands({"transition": False}, "base"))
            result = subprocess.run(
                command + ["--config=" + str(profile), "--iterations=1", "--revs=1", "--warmup=1"],
                text=True,
                capture_output=True,
            )
            output = result.stdout + result.stderr
            self.assertEqual(1, result.returncode, output)
            self.assertIn("DiagnosticFailureBench", output)
            self.assertIn("benchThrows", output)
            self.assertIn("known benchmark exception", output)

    def test_native_filters_partition_the_actual_gate_inventory(self):
        with tempfile.TemporaryDirectory() as directory:
            outputs = []
            for name, pattern in [("all", None)] + [(row[0], row[2]) for row in compare.selections(True)]:
                args = ["--filter=" + pattern] if pattern else []
                result, output = self.run_selection(directory, name, args)
                self.assertEqual(0, result.returncode, result.stdout + result.stderr)
                outputs.append(self.subjects(output))
            all_subjects, core, startup = outputs
            self.assertEqual(set(compare.STARTUP_SUBJECTS), startup)
            self.assertFalse(core & startup)
            self.assertEqual(all_subjects, core | startup)

    def test_native_assert_overrides_default_instead_of_adding_to_it(self):
        with tempfile.TemporaryDirectory() as directory:
            selection = ["--filter=^" + re.escape(compare.STARTUP_SUBJECTS[0]) + "$"]
            baseline, output = self.run_selection(directory, "base", selection + ["--tag=assertion_override_base", "--store"])
            self.assertEqual(0, baseline.returncode, baseline.stdout + baseline.stderr)
            reference = selection + ["--ref=assertion_override_base"]
            strict, _ = self.run_selection(directory, "strict", reference, time=130)
            self.assertNotEqual(0, strict.returncode)
            relaxed, _ = self.run_selection(directory, "relaxed", reference + ["--assert=" + compare.ASSERTION.format(40)], time=130)
            self.assertEqual(0, relaxed.returncode, relaxed.stdout + relaxed.stderr)
            excessive, _ = self.run_selection(directory, "excessive", reference + ["--assert=" + compare.ASSERTION.format(40)], time=141)
            self.assertNotEqual(0, excessive.returncode)


if __name__ == "__main__":
    unittest.main()
