#!/bin/bash

# Pass in any number of ANSI SGR codes.
#
# Code reference:
#   https://en.wikipedia.org/wiki/ANSI_escape_code#SGR_(Select_Graphic_Rendition)_parameters
# Credit:
#   https://superuser.com/a/1119396
sgr() {
  local codes=${1:-0}
  shift

  for c in "$@"; do
    codes="$codes;$c"
  done

  echo $'\e'"[${codes}m"
}

_COLOR_BOLD="$(sgr 1)"
_COLOR_FAINT="$(sgr 2)"
_COLOR_RED="$(sgr 31)"
_COLOR_YELLOW="$(sgr 33)"
_COLOR_BLACK="$(sgr 30)"
_COLOR_GREEN="$(sgr 32)"
_COLOR_DEFAULT="$(sgr 0)"

function trace() {
  set -x
}

function untrace() {
  set +x
}

# An alternative to echo when debugging.
function dump() {
  printf "[%s] %s: %s\n" "${_COLOR_YELLOW}DUMP${_COLOR_DEFAULT}" \
    "${_COLOR_GREEN}${BASH_SOURCE[1]}:${BASH_LINENO[0]}" \
    "${_COLOR_DEFAULT}$*"
}

# Dump and Die.
function dd() {
  printf "[%s] %s: %s\n" "${_COLOR_RED}DUMP${_COLOR_DEFAULT}" \
    "${_COLOR_GREEN}${BASH_SOURCE[1]}:${BASH_LINENO[0]}" \
    "${_COLOR_DEFAULT}$*"

  kill -9 $$
}

function debug_var() {
  local var_name=$1
  local var_value=${!var_name}
  printf "[%s] %s: %s=%s\n" "${_COLOR_FAINT}DEBUG${_COLOR_DEFAULT}" \
    "${_COLOR_GREEN}${BASH_SOURCE[1]}:${BASH_LINENO[0]}" \
    "$var_name" "$var_value"
}
