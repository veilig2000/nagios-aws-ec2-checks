#!/usr/bin/php
<?php

require_once __DIR__ . '/vendor/autoload.php';

// Commandline arguments are used to set the correct parameters
$region = $argv[1];
$warningLevel = $argv[2];
$criticalLevel = $argv[3];

use Aws\Ec2\Ec2Client;
$options = [
    'region' => $region,
    'version' => '2015-04-15',
];
// Settings up the AWS API connection
$ec2client = new Ec2Client($options);

$result = $ec2client->describeInstanceStatus([
    'IncludeAllInstances' => true,
    'Filters' => [
        [
            'Name' => 'event.code',
            'Values' => ['instance-stop', 'instance-retirement']
        ]
    ],
]);

$criticalAlerts = $warningAlerts = 0;
if ($result->hasKey('InstanceStatuses')) {
    $instances = $result->get('InstanceStatuses');

    if (count($instances) > 0) {
        $today = new DateTime('NOW');
        echo count($instances) . " unhealthy instance(s)\n";
        foreach ($instances as $instance) {
            echo "{Instance [" . $instance['InstanceId'] . "] ";
            foreach ($instance['Events'] as $event) {
                echo sprintf(
                    '%s on (%s) - %s',
                    $event['Code'],
                    $event['NotBefore']->format('m-d-Y'),
                    $event['Description']
                );

                $diff = $today->diff($event['NotBefore']);
                if ($diff->d <= $criticalLevel) {
                    ++$criticalAlerts;
                } elseif ($diff->d <= $warningLevel) {
                    ++$warningAlerts;
                }
            }
            echo "}\n";
        }
    }
}

if ($criticalAlerts >= $criticalLevel) {
  exit(2);
} elseif ($warningAlerts >= $warningLevel) {
  exit(1);
}
