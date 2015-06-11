<?php
    function __autoload($class_name) {
        require 'inc/gcm_push_notifications/' . str_replace('\\', '/', $class_name) . '.php';
    }

    use DeviceDetector\DeviceDetector;
    use DeviceDetector\Parser\Device\DeviceParserAbstract;

    // OPTIONAL: Set version truncation to none, so full versions will be returned
    // By default only minor versions will be returned (e.g. X.Y)
    // for other options see VERSION_TRUNCATION_* constants in DeviceParserAbstract class

    $dd = new DeviceDetector($_SERVER['HTTP_USER_AGENT']);

    // OPTIONAL: Set caching method
    // By default static cache is used, which works best within one php process (memory array caching)
    // To cache across requests use caching in files or memcache
    // $dd->setCache(new Doctrine\Common\Cache\PhpFileCache('./tmp/'));

    // OPTIONAL: If called, getBot() will only return true if a bot was detected  (speeds up detection a bit)
    // $dd->discardBotInformation();

    $dd->parse();

    if ($dd->getModel()) {
        print $dd->getModel();
    } else {
        printf('%s %1.1f for %s', $dd->getClient()['name'], $dd->getClient()['version'], $dd->getOs()['name']);
    }
