<?php

declare(strict_types=1);

// Wrong on purpose: a Gacela config returns `callable(GacelaConfig): void`, and
// this returns an array. What a package author writes when they forget the
// `return static function (...)`.
return ['audit' => true];
