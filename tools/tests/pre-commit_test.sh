#!/bin/bash

function set_up() {
  SCRIPT="$(current_dir)"/../git-hooks/pre-commit.sh
}

function test_pre_commit() {
  mock composer echo "mocked composer"

  assert_match_snapshot "$($SCRIPT)"
}
