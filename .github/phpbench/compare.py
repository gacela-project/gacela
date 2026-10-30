"""Keep base and head selections identical, including the cache-read transition."""

import json
import re
import subprocess
import sys


FILE_CACHE = "src/Framework/Cache/FileCache.php"
STARTUP_SUBJECTS = (
    r"\GacelaTest\Benchmark\Bootstrap\BootstrapBench::bench_bootstrap_warm",
    r"\GacelaTest\Benchmark\Bootstrap\BootstrapBench::bench_bootstrap_auto_warmed",
    r"\GacelaTest\Benchmark\Config\ConfigInitBench::bench_config_init_warm",
    r"\GacelaTest\Benchmark\Config\ConfigInitBench::bench_config_init_auto_warmed",
)
STARTUP_PATTERN = "(?:" + "|".join(re.escape(name) for name in STARTUP_SUBJECTS) + ")"
ASSERTION = "mode(variant.time.avg) <= mode(baseline.time.avg) +/- {}%"


def source_at(ref):
    if not re.fullmatch(r"[0-9a-f]{40}", ref):
        raise ValueError("Expected a full commit SHA")
    return subprocess.check_output(
        ["git", "show", f"{ref}:{FILE_CACHE}"], text=True
    )


def introduces_guard(base, head):
    # Require the actual early return before the read, not just a new method.
    read_guard = (
        r"public static function readArray\(string \$file\): \?array\s*\{\s*"
        r"if \(!self::isSafeToRead\(\$file\)\)\s*\{\s*return null;\s*\}"
    )
    head = re.sub(r"/\*.*?\*/|//[^\n]*", "", head, flags=re.DOTALL)
    return (
        "isSafeToRead" not in base
        and "public static function isSafeToRead(string $file): bool" in head
        and re.search(read_guard, head) is not None
    )


def selections(transition):
    if transition:
        return [
            ("base_gate_core", "gate", f"^(?!{STARTUP_PATTERN}$).*$", 10),
            ("base_gate_startup", "gate", f"^{STARTUP_PATTERN}$", 40),
        ]
    return [("base_gate", "gate", None, 10)]


def commands(plan, phase):
    groups = selections(plan["transition"])
    if phase == "base":
        groups += [("base_info", "informational", None, None)]
    elif phase == "info":
        groups = [("base_info", "informational", None, None)]
    elif phase != "gate":
        raise ValueError("Expected base, gate or info phase")
    for tag, group, pattern, tolerance in groups:
        command = ["vendor/bin/phpbench", "run", f"--group={group}", "--progress=plain"]
        if pattern:
            command.append(f"--filter={pattern}")
        if phase == "base":
            command += [f"--tag={tag}", "--store"]
        else:
            command += [f"--ref={tag}", "--report=aggregate"]
            if tolerance is not None:
                command.append("--assert=" + ASSERTION.format(tolerance))
        yield command


def main():
    phase, *arguments = sys.argv[1:]
    if phase == "plan":
        base, head = arguments
        transition = introduces_guard(source_at(base), source_at(head))
        print(json.dumps({"base": base, "head": head, "transition": transition}))
        return
    with open(arguments[0], encoding="utf-8") as stream:
        plan = json.load(stream)
    expected = plan["base"] if phase == "base" else plan["head"]
    actual = subprocess.check_output(["git", "rev-parse", "HEAD"], text=True).strip()
    if actual != expected:
        raise ValueError("Checkout does not match the captured benchmark commit")
    for command in commands(plan, phase):
        print(json.dumps(command), flush=True)
        subprocess.run(command, check=True)


if __name__ == "__main__":
    main()
