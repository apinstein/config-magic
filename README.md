ConfigMagic
===========

A simple organizational tool to help you manage config files for your web app.

INSTALLATION
------------

```sh
$ composer global require apinstein/config_magic
```

HOW IT WORKS
------------

A typical web app has 1 or more config files. For instance, your app might have the following files:
httpd.conf      (apache config)
webapp.conf     (framework config)
sh.conf         (shell script config)

A typical web app is also run on more than one server. For instance:
dev-alan
dev-jason
staging
production

Configuring your web app to run on a new envirionment can quickly become a painful process as you have to remember to edit multiple files with correct information.
In my experience, a lot of time is wasted trying to:
    - Bootstrap the new environment one config file at a time
    - Remember all of the different places and values that need to be configued
    - Messing with regex replacements to try to batch-replace things listed repetitively

ConfigMagic solves these problems by letting you quickly and easily set up template files for each config file.
Your template file can contain default data for each variable substitution used by your configs.
You then need only set up a profile.ini for each machine profile and declare any non-default data you need to use.
ConfigMagic then writes out all config files for a given profile.

ConfigMagic comes with a command-line utility "cfg" that lets you quickly manage this process.

EXAMPLE
-------

Initialize ConfigMagic for this project.

```sh
$ cfg -i
```

Set up an example config file

```sh
$ echo "myProfile = ##PROFILE##" > config/templates/example.conf
$ echo "myVar = ##my.var##" >> config/templates/example.conf
```

Set up an empty profile

```sh
$ echo "my.var = 10" > config/profiles/dev.ini
```

Build all config files for the "dev" profile

```sh
$ cfg dev
```

Check

```sh
$ cat config/example.conf
```

Output:
```
myProfile = dev
myVar = 10
```

VARIABLE SUBSTITUION
--------------------

Variables in the template files take the form of:

`##my.var##`

And will be replace by the corresponding ini file variable:

`my.var`

Variables in the profile.ini file override the default values set in the config.ini file.

Variables can also use variable substituion on variables previously defined (ie higher in the file).

a = foo
b = ##a##-bar       # b = foo-bar

The following additional variables are automatically defined:

`##CONFIG##`          => the current config name
`##PROFILE##`         => the current profile name
`##CONFIG_DIR##`      => the absolute path to the config dir
`##OUTPUT_DIR##`      => the absolute path to the output dir
`##TEMPLATES_DIR##`   => the absolute path to the config/templates dir

Any variable substitution in the template file that isn't defined in any ini file will be skipped and a warning will print out.

DYNAMIC TEMPLATES
-----------------

All templates are treated as PHP code, which means that you can use normal PHP syntax to dynamically generate config files based on profile data.

All variables available in normal variable substition (in form of ##varname##) are available in the template, via the $profileData variable. For instance:

```php
<?php if (!$profileData['##isProduction##']): ?>
'classname' => 'DebugPDO',
<?php endif; ?>
```

NOTE: if you are actually generating a php file, you will need to escape php tags so that they are not seen as a "PHP Start Tag":

long php tags
`<<?php ?>?php`

short php tags
`<<? ?>?`

DYNAMIC VARIABLES
-----------------

In some cases you may want to programmatically munge the profile data directly rather than in the template files via dynamic templates.

A good example of this is to call realpath() on a path at the variable level so you don't have to remember to do this in each template.

This can be done with this simple syntax:

```
project.dir = "<?php realpath('##OUTPUT_DIR##/..') ?>"
```

The php processing will occur *after* the variable substitution has processed.

CONCLUSION
----------

Getting started with ConfigMagic is very easy and provides a solid organizational framework to manage your config files. I hope you enjoy it.

Please feel free to send comments or suggestions my way.
