# Contributing to Gacela

## Welcome!

We look forward to your contributions!

## We have a Code of Conduct

Please note that this project is released with a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in this project you agree to abide by its terms.

## Any contributions you make will be under the MIT License

When you submit code changes, your submissions are understood to be under the same [MIT](https://github.com/gacela-project/gacela/blob/master/LICENSE) that covers the project. By contributing to this project, you agree that your contributions will be licensed under its MIT.

## Write bug reports with detail, background, and sample code

In your bug report, please provide the following:

* A quick summary and/or background
* Steps to reproduce
    * Be specific!
    * Give sample code if you can.
* What you expected would happen
* What actually happens
* Notes (possibly including why you think this might be happening, or stuff you tried that didn't work)

Please post code and output as text ([using proper markup](https://guides.github.com/features/mastering-markdown/)). 
Do not post screenshots of code or output.

## Workflow for Pull Requests

1. Fork/clone the repository.
2. Install the vendor dependencies with `composer update`.
3. Create your branch from `master` if you plan to implement new functionality or change existing code significantly;
   create your branch from the oldest branch that is affected by the bug if you plan to fix a bug.
4. Implement your change and add tests for it.
5. Ensure the test suite passes.
6. Ensure the code complies with our coding guidelines (see below).
7. Send that pull request!

Please make sure you have [set up your username and email address](https://git-scm.com/book/en/v2/Getting-Started-First-Time-Git-Setup) for use with Git. Strings such as `silly nick name <root@localhost>` look really stupid in the commit history of a project.

## Coding Guidelines

This project comes with some configuration files (located at `/psalm.xml` & `/phpstan.neon`) that you can use to perform static analysis (with a focus on type checking):

```bash
$ ./vendor/bin/psalm
$ ./vendor/bin/phpstan
```

This project comes with a configuration file (located at `/.php-cs-fixer.dist.php` in the repository) that you can use to (re)format your source code for compliance with this project's coding guidelines:

```bash
$ ./vendor/bin/php-cs-fixer fix
```

Please understand that we will not accept a pull request when its changes violate this project's coding guidelines.

## Running Gacela's test suite

Once you've installed all composer dependencies, you can simply test all suites running the following composer script:

```bash
$ composer test-all
```

You can see more composer scripts inside the `/composer.json` file.

## Git Hooks

You can verify all your commits will pass the CI (coding guidelines, static analyzers, and tests) by enabling the git
pre-commit hook that will trigger all of them before creating a new commit. Don't worry, it usually takes a couple of
seconds.

You can add the git hook running the following script:

```bash
$ php tools/git-hooks/init.php
```
This command works on any system, including Windows.
