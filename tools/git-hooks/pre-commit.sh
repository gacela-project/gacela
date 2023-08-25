#!/bin/bash

set -e

composer quality
composer phpunit
