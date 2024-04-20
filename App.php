<?php 

require_once('Container.php');

interface Logger
{
    public function log($message);
}

class FileLogger implements Logger
{
    public function __construct($filePath)
    {
        // ... 
    }

    public function log($message)
    {
        // ...
    }
}

$container = new Container();

// Bind an interface to a concrete implementation
$container->bind(Logger::class, FileLogger::class);

// Resolve an instance of Logger
$logger = $container->make(Logger::class, ['filePath' => '/path/to/log.txt']);
$logger->log('Hello, World!');