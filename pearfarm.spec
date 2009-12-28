<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
            ->setName('config_magic')
            ->setChannel('apinstein.dev.pearfarm.org')
            ->setSummary('A simple tool for managing multiple config files on multiple deployment scenarios.')
            ->setDescription('By creating *profiles* and *templates* you can easily create a full set of config files for a certain machine in seconds.')
            ->setReleaseVersion('1.0.0')
            ->setReleaseStability('stable')
            ->setApiVersion('1.0.0')
            ->setApiStability('stable')
            ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
            ->setNotes('Initial pear release.')
            ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
            ->addGitFiles()
            ->addExecutable('cfg')
            ;
