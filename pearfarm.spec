<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
            ->setName('config_magic')
            ->setChannel('apinstein.pearfarm.org')
            ->setSummary('A simple tool for managing multiple config files on multiple deployment scenarios.')
            ->setDescription('See http://github.com/apinstein/config-magic')
            ->setReleaseVersion('1.0.3')
            ->setReleaseStability('stable')
            ->setApiVersion('1.0.0')
            ->setApiStability('stable')
            ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
            ->setNotes('Initial pear release.')
            ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
            ->addGitFiles()
            ->addExecutable('cfg')
            ;
