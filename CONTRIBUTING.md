# Contributing to Respect\Rest

Contributions to Respect\Rest are always welcome. You make our lives easier by
sending us your contributions through GitHub pull requests.

Pull requests for bug fixes must be based on the current stable branch whereas
pull requests for new features must be based on `master`.

Due to time constraints, we are not always able to respond as quickly as we
would like. Please do not take delays personal and feel free to remind us here,
on IRC, or on Gitter if you feel that we forgot to respond.

## Using Respect\Rest From a Git Checkout

The following commands can be used to perform the initial checkout of Respect\Rest:

```shell
git clone git://github.com/Respect/Rest.git
cd Rest
```

Retrieve Respect\Rest's dependencies using [Composer](http://getcomposer.org/):

```shell
composer install
```

## Running Tests

After run `composer install` on the library's root directory you must run PHPUnit.

### Linux

You can test the project using the commands:
```shell
$ vendor/bin/phpunit
```

### Windows

You can test the project using the commands:
```shell
> vendor\bin\phpunit
```

No test should fail.

You can tweak the PHPUnit's settings by copying `phpunit.xml.dist` to `phpunit.xml`
and changing it according to your needs.

## Standards

We are trying to follow the [PHP-FIG](http://www.php-fig.org)'s standards, so
when you send us a pull request, be sure you are following them.

## Sending your code to us

Please see http://help.github.com/pull-requests/.
