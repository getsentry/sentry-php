<?php

class DeprecatedClasses extends PHPUnit_Framework_TestCase
{
    public function testAllDeprecated()
    {
        $classes = array('Raven_Breadcrumbs', 'Raven_Client', 'Raven_Context', 'Raven_CurlHandler',
                         'Raven_ErrorHandler', 'Raven_Exception', 'Raven_Processor', 'Raven_ReprSerializer',
                         'Raven_SanitizeDataProcessor', 'Raven_Serializer', 'Raven_TransactionStack',
                         'Raven_Util', 'Raven_Breadcrumbs_ErrorHandler', 'Raven_Breadcrumbs_MonologHandler'.
                         'Raven_Autoloader');
        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class));
        }
    }

    public function testDeprecatedAutoloader()
    {
        /** @noinspection PhpDeprecationInspection */
        Raven_Autoloader::register();
    }
}
