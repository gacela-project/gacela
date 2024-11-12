#!/bin/bash

# Ensure bashdep is installed
[ ! -f lib/bashdep ] && {
  mkdir -p lib
  curl -sLo lib/bashdep \
    https://github.com/Chemaclass/bashdep/releases/download/0.1/bashdep
  chmod +x lib/bashdep
}

# Add latest bashunit release to your dependencies
DEPENDENCIES=(
  "https://github.com/TypedDevs/bashunit/releases/download/0.18.0/bashunit"
  "https://github.com/Chemaclass/bash-dumper/releases/download/0.1/dumper.sh@dev"
)

# Load, configure and run bashdep
source lib/bashdep
bashdep::setup dir="lib" dev-dir="src/dev" silent=false
bashdep::install "${DEPENDENCIES[@]}"
