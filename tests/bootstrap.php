<?php declare(strict_types=1);

use Doctrine\Common\Annotations\AnnotationRegistry;

AnnotationRegistry::registerLoader(function($class) {
    return class_exists($class);
});
