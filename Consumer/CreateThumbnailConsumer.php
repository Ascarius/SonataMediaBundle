<?php

/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Consumer;

use Sonata\NotificationBundle\Consumer\ConsumerInterface;
use Sonata\NotificationBundle\Consumer\ConsumerEvent;
use Sonata\MediaBundle\Model\MediaManagerInterface;
use Sonata\MediaBundle\Provider\Pool;
use Sonata\MediaBundle\Thumbnail\ThumbnailInterface;
use Sonata\NotificationBundle\Exception\HandlingException;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Sonata\NotificationBundle\Backend\BackendInterface;

class CreateThumbnailConsumer implements ConsumerInterface
{
    protected $mediaManager;

    protected $pool;

    protected $container;

    /**
     * @param \Sonata\MediaBundle\Model\MediaManagerInterface           $mediaManager
     * @param \Sonata\MediaBundle\Provider\Pool                         $pool
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct(MediaManagerInterface $mediaManager, Pool $pool, ContainerInterface $container)
    {
        $this->mediaManager = $mediaManager;
        $this->pool         = $pool;
        $this->container    = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ConsumerEvent $event)
    {
        $media = $this->mediaManager->findOneBy(array(
            'id' => $event->getMessage()->getValue('mediaId')
        ));

        // solve race condition between message queue and database transaction
        $media->setProviderReference($event->getMessage()->getValue('providerReference'));

        if (!$media) {
            throw new HandlingException(sprintf('Media not found - id: %s', $event->getMessage()->getValue('mediaId')));
        }

        try {
            $this->getThumbnail($event)->generate($this->pool->getProvider($media->getProviderName()), $media);
        } catch (\LogicException $e) {
            throw new HandlingException(sprintf('Error while generating exception for media.id: %s', $event->getMessage()->getValue('mediaId')), 0, $e);
        }
    }

    /**
     * @param ConsumerEvent $event
     *
     * @return ThumbnailInterface
     */
    protected function getThumbnail(ConsumerEvent $event)
    {
        $thumbnail = $this->container->get($event->getMessage()->getValue('thumbnailId'));

        if (!$thumbnail instanceof ThumbnailInterface) {
            throw new HandlingException(sprintf('Invalid thumbnail instance requested - id: %s', $event->getMessage()->getValue('thumbnailId')));
        }

        return $thumbnail;
    }
}