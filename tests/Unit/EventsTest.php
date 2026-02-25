<?php

test('all events can be instantiated', function () {
    $namespace = 'Frolax\\Payment\\Events\\';
    $files = glob(__DIR__.'/../../src/Events/*.php');

    foreach ($files as $file) {
        $className = $namespace.basename($file, '.php');
        $reflection = new ReflectionClass($className);

        $constructor = $reflection->getConstructor();
        $args = [];

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();

                    continue;
                }

                $type = $param->getType();
                $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

                $resolved = null;
                foreach ($types as $t) {
                    if ($t instanceof ReflectionNamedType) {
                        if (! $t->isBuiltin() && class_exists($t->getName())) {
                            $resolved = Mockery::mock($t->getName())->makePartial();
                            break;
                        }
                        $name = $t->getName();
                        if ($name === 'string') {
                            $resolved = 'dummy';
                            break;
                        } elseif ($name === 'int') {
                            $resolved = 1;
                            break;
                        } elseif ($name === 'float') {
                            $resolved = 1.0;
                            break;
                        } elseif ($name === 'bool') {
                            $resolved = false;
                            break;
                        } elseif ($name === 'array') {
                            $resolved = [];
                            break;
                        }
                    }
                }
                $args[] = $resolved;
            }
        }

        $event = $reflection->newInstanceArgs($args);
        expect($event)->toBeInstanceOf($className);
    }
});
