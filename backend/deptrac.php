<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassNameRegexConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/*
 * Architectural boundary enforcement. This file IS the dependency rule from
 * CLAUDE.md ("Backend architecture — the dependency rule") in executable form:
 * a violation here is a CI failure, not a code-review opinion.
 *
 * The two load-bearing rules:
 *  - SharedDomain depends on NOTHING (the shared kernel is the most expensive
 *    thing to change, so it is kept starved).
 *  - Domain depends only on SharedDomain — no Symfony, no Doctrine, ever.
 */
return static function (DeptracConfig $config): void {
    $config
        ->paths('./src')
        ->layers(
            $sharedDomain = Layer::withName('SharedDomain')->collectors(
                DirectoryConfig::create('src/Shared/Domain/.*'),
            ),
            $sharedInfrastructure = Layer::withName('SharedInfrastructure')->collectors(
                DirectoryConfig::create('src/Shared/Infrastructure/.*'),
            ),
            $domain = Layer::withName('Domain')->collectors(
                DirectoryConfig::create('src/VendingMachine/Domain/.*'),
            ),
            $application = Layer::withName('Application')->collectors(
                DirectoryConfig::create('src/VendingMachine/Application/.*'),
            ),
            $delivery = Layer::withName('Delivery')->collectors(
                DirectoryConfig::create('src/VendingMachine/Delivery/.*'),
            ),
            $infrastructure = Layer::withName('Infrastructure')->collectors(
                DirectoryConfig::create('src/VendingMachine/Infrastructure/.*'),
            ),
            // NOTE: ConfigurableCollectorConfig::create() doubles every
            // backslash (YAML-compat escaping), so patterns here use a SINGLE
            // logical backslash — create('/^App\Kernel$/') becomes the regex
            // /^App\\Kernel$/ at match time.
            $kernel = Layer::withName('Kernel')->collectors(
                ClassNameRegexConfig::create('/^App\Kernel$/'),
            ),
            $symfony = Layer::withName('Symfony')->collectors(
                ClassNameRegexConfig::create('/^Symfony\\/'),
            ),
            $doctrine = Layer::withName('Doctrine')->collectors(
                ClassNameRegexConfig::create('/^Doctrine\\/'),
            ),
            $psr = Layer::withName('Psr')->collectors(
                ClassNameRegexConfig::create('/^Psr\\/'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($sharedDomain), // depends on nothing
            Ruleset::forLayer($domain)->accesses($sharedDomain),
            Ruleset::forLayer($application)->accesses($domain, $sharedDomain),
            Ruleset::forLayer($delivery)->accesses($application, $domain, $sharedDomain, $symfony, $psr),
            Ruleset::forLayer($infrastructure)->accesses(
                $domain,
                $application,
                $sharedDomain,
                $sharedInfrastructure,
                $symfony,
                $doctrine,
                $psr,
            ),
            Ruleset::forLayer($sharedInfrastructure)->accesses($sharedDomain, $symfony, $doctrine, $psr),
            Ruleset::forLayer($kernel)->accesses($symfony),
        )
    ;
};
