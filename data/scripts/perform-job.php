<?php
/**
 * Perform a job.
 */

use Omeka\Job\Strategy\SynchronousStrategy;
use Omeka\Model\Entity\Job;

require dirname(dirname(__DIR__)) . '/bootstrap.php';

$application = Omeka\Mvc\Application::init(require OMEKA_PATH . '/config/application.config.php');
$serviceLocator = $application->getServiceManager();
$entityManager = $serviceLocator->get('Omeka\EntityManager');
$logger = $serviceLocator->get('Omeka\Logger');

$options = getopt('j:p:');
if (!isset($options['j'])) {
    $logger->err('No job ID given; use -j <id>');
    exit;
}
if (!isset($options['p'])) {
    $logger->err('No base path given; use -p <basePath>');
    exit;
}

$serviceLocator->get('ViewHelperManager')->get('BasePath')->setBasePath($options['p']);

$job = $entityManager->find('Omeka\Model\Entity\Job', $options['j']);
if (!$job) {
    $logger->err('There is no job with the given ID');
    exit;
}

// Set the job owner as the authenticated identity.
$owner = $job->getOwner();
if ($owner) {
    $serviceLocator->get('Omeka\AuthenticationService')->getStorage()->write($owner);
}

$job->setPid(getmypid());
$entityManager->flush();

// From here all processing is synchronous.
$strategy = new SynchronousStrategy($serviceLocator);
$serviceLocator->get('Omeka\JobDispatcher')->send($job, $strategy);

$job->setPid(null);
$entityManager->flush();
