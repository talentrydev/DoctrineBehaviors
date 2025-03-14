<?php

declare(strict_types=1);

namespace Knp\DoctrineBehaviors\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Knp\DoctrineBehaviors\Contract\Entity\LoggableInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class LoggableEventListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $postPersistEventArgs): void
    {
        $object = $postPersistEventArgs->getObject();
        if (!$object instanceof LoggableInterface) {
            return;
        }

        $createLogMessage = $object->getCreateLogMessage();
        $this->logger->log(LogLevel::INFO, $createLogMessage);

        $this->logChangeSet($postPersistEventArgs);
    }

    public function postUpdate(PostUpdateEventArgs $postUpdateEventArgs): void
    {
        $object = $postUpdateEventArgs->getObject();
        if (!$object instanceof LoggableInterface) {
            return;
        }

        $this->logChangeSet($postUpdateEventArgs);
    }

    public function preRemove(PreRemoveEventArgs $preRemoveEventArgs): void
    {
        $object = $preRemoveEventArgs->getObject();

        if ($object instanceof LoggableInterface) {
            $this->logger->log(LogLevel::INFO, $object->getRemoveLogMessage());
        }
    }

    /**
     * Logs entity changeset
     */
    private function logChangeSet(PostPersistEventArgs|PostUpdateEventArgs $eventArgs): void
    {
        $objectManager = $eventArgs->getObjectManager();
        $unitOfWork = $objectManager->getUnitOfWork();
        $object = $eventArgs->getObject();

        $entityClass = $object::class;
        $classMetadata = $objectManager->getClassMetadata($entityClass);

        /** @var LoggableInterface $object */
        $unitOfWork->computeChangeSet($classMetadata, $object);
        $changeSet = $unitOfWork->getEntityChangeSet($object);

        $message = $object->getUpdateLogMessage($changeSet);

        if ($message === '') {
            return;
        }

        $this->logger->log(LogLevel::INFO, $message);
    }
}
