#!/bin/bash

function set_up() {
  SCRIPT="$(current_dir)"/../git-hooks/pre-commit.sh
}

function test_pre_commit() {
  spy composer

  eval "$SCRIPT"

  assert_have_been_called_times 2 composer
  assert_have_been_called_with "quality" composer 1
  assert_have_been_called_with "phpunit" composer 2
}
