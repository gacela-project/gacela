#!/bin/bash

function test_pre_commit() {
  spy composer

  "$(dirname "${BASH_SOURCE[0]}")"/../git-hooks/pre-commit.sh

  assert_have_been_called_times 2 composer
}
