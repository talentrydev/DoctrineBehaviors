<?php

declare(strict_types=1);

namespace Knp\DoctrineBehaviors\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Knp\DoctrineBehaviors\Contract\Entity\SluggableInterface;
use Knp\DoctrineBehaviors\Repository\DefaultSluggableRepository;

#[AsDoctrineListener(event: Events::loadClassMetadata)]
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class SluggableEventListener
{
    /**
     * @var string
     */
    private const SLUG = 'slug';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DefaultSluggableRepository $defaultSluggableRepository,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $loadClassMetadataEventArgs): void
    {
        $classMetadata = $loadClassMetadataEventArgs->getClassMetadata();
        if ($this->shouldSkip($classMetadata)) {
            return;
        }

        $classMetadata->mapField([
            'fieldName' => self::SLUG,
            'type' => 'string',
            'nullable' => true,
        ]);
    }

    public function prePersist(PrePersistEventArgs $prePersistEventArgs): void
    {
        $this->processPreAndPostPersistEventArgs($prePersistEventArgs);
    }

    public function preUpdate(PreUpdateEventArgs $preUpdateEventArgs): void
    {
        $this->processPreAndPostPersistEventArgs($preUpdateEventArgs);
    }

    private function shouldSkip(ClassMetadataInfo $classMetadataInfo): bool
    {
        if (! is_a($classMetadataInfo->getName(), SluggableInterface::class, true)) {
            return true;
        }

        return $classMetadataInfo->hasField(self::SLUG);
    }

    private function processPreAndPostPersistEventArgs(PrePersistEventArgs|PreUpdateEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();
        if (!$object instanceof SluggableInterface) {
            return;
        }

        $object->generateSlug();

        if ($object->shouldGenerateUniqueSlugs()) {
            $this->generateUniqueSlugFor($object);
        }
    }

    private function generateUniqueSlugFor(SluggableInterface $sluggable): void
    {
        $i = 0;
        $slug = $sluggable->getSlug();

        $uniqueSlug = $slug;

        while (! (
            $this->defaultSluggableRepository->isSlugUniqueFor($sluggable, $uniqueSlug)
            && $this->isSlugUniqueInUnitOfWork($sluggable, $uniqueSlug)
        )) {
            $uniqueSlug = $slug . '-' . ++$i;
        }

        $sluggable->setSlug($uniqueSlug);
    }

    private function isSlugUniqueInUnitOfWork(SluggableInterface $sluggable, string $uniqueSlug): bool
    {
        $scheduledEntities = $this->getOtherScheduledEntities($sluggable);
        foreach ($scheduledEntities as $scheduledEntity) {
            if ($scheduledEntity->getSlug() === $uniqueSlug) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return SluggableInterface[]
     */
    private function getOtherScheduledEntities(SluggableInterface $sluggable): array
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();

        $uowScheduledEntities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
        ];

        $scheduledEntities = [];
        foreach ($uowScheduledEntities as $uowScheduledEntity) {
            if ($uowScheduledEntity instanceof SluggableInterface && $sluggable !== $uowScheduledEntity) {
                $scheduledEntities[] = $uowScheduledEntity;
            }
        }

        return $scheduledEntities;
    }
}
