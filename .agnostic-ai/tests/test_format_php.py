import json
import os
import pathlib
import shutil
import subprocess
import tempfile
import unittest


SOURCE = pathlib.Path(__file__).resolve().parents[1]


class FormatPhp(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.root = pathlib.Path(temporary.name)
        self.log = self.root / "formatted.jsonl"
        self.env = dict(os.environ, FORMAT_LOG=str(self.log))
        self.env.pop("CLAUDE_PROJECT_DIR", None)
        (self.root / "agnostic-ai.yaml").write_text("version: 1\ntargets: [claude, codex]\n")
        for relative in ["hooks/format-php.yaml", "scripts/format-php.sh"]:
            destination = self.root / ".agnostic-ai" / relative
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(SOURCE / relative, destination)
        fixer = self.root / "vendor/bin/php-cs-fixer"
        fixer.parent.mkdir(parents=True)
        fixer.write_text(
            "#!/usr/bin/env python3\n"
            "import json, os, sys\n"
            "with open(os.environ['FORMAT_LOG'], 'a') as log:\n"
            "    log.write(json.dumps(sys.argv[1:]) + '\\n')\n"
        )
        fixer.chmod(0o755)
        self.run_command(["agnostic-ai", "sync"])

    def run_command(self, command, **kwargs):
        result = subprocess.run(command, cwd=self.root, env=self.env, text=True, capture_output=True, **kwargs)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        return result

    def calls(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()] if self.log.exists() else []

    def test_both_targets_format_paths_with_spaces_and_shell_characters(self):
        name = "edited $(touch unexpected).php"
        (self.root / name).write_text("<?php\n")
        result = self.run_command([
            "agnostic-ai", "hook", "run", "format-php", "--edit", name,
            "--expect", "allow", "--format", "json",
        ])
        self.assertIn('"target":"claude"', result.stdout.replace(" ", ""))
        self.assertIn('"target":"codex"', result.stdout.replace(" ", ""))
        self.assertEqual(self.calls(), [["fix", "--quiet", "--", name]] * 2)
        self.assertFalse((self.root / "unexpected").exists())

    def test_non_php_and_deleted_paths_are_not_formatted(self):
        (self.root / "notes.txt").write_text("text\n")
        for name in ["notes.txt", "removed.php"]:
            self.run_command(["agnostic-ai", "hook", "run", "format-php", "--edit", name, "--expect", "allow"])
        self.assertEqual(self.calls(), [])

    def test_missing_project_directory_does_not_format_from_the_wrong_directory(self):
        path = self.root / "edited.php"
        path.write_text("<?php\n")
        self.env["CLAUDE_PROJECT_DIR"] = str(self.root / "missing")
        self.env["AGNOSTIC_AI_TARGET"] = "claude"
        self.run_command(
            ["bash", str(SOURCE / "scripts/format-php.sh")],
            input=json.dumps({"tool_name": "Edit", "tool_input": {"file_path": str(path)}}),
        )
        self.assertEqual(self.calls(), [])


if __name__ == "__main__":
    unittest.main()
