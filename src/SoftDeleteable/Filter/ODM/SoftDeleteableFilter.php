<?php

namespace Gedmo\SoftDeleteable\Filter\ODM;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Query\Filter\BsonFilter;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

class SoftDeleteableFilter extends BsonFilter
{
    protected $listener;
    protected $documentManager;
    protected $disabled = [];

    /**
     * Gets the criteria part to add to a query.
     *
     * @return array The criteria array, if there is available, empty array otherwise
     */
    public function addFilterCriteria(ClassMetadata $targetEntity): array
    {
        $class = $targetEntity->getName();
        if (isset($this->disabled[$class]) && true === $this->disabled[$class]) {
            return [];
        }
        if (isset($this->disabled[$targetEntity->rootDocumentName]) && true === $this->disabled[$targetEntity->rootDocumentName]) {
            return [];
        }

        $config = $this->getListener()->getConfiguration($this->getDocumentManager(), $targetEntity->name);

        if (!isset($config['softDeleteable']) || !$config['softDeleteable']) {
            return [];
        }

        $column = $targetEntity->fieldMappings[$config['fieldName']];

        if (isset($config['timeAware']) && $config['timeAware']) {
            return [
                '$or' => [
                    [$column['fieldName'] => null],
                    [$column['fieldName'] => ['$gt' => new \DateTime('now')]],
                ],
            ];
        }

        return [
            $column['fieldName'] => null,
        ];
    }

    protected function getListener()
    {
        if (null === $this->listener) {
            $evm = $this->getDocumentManager()->getEventManager();

            foreach ($evm->getListeners() as $listeners) {
                foreach ($listeners as $listener) {
                    if ($listener instanceof SoftDeleteableListener) {
                        $this->listener = $listener;

                        break 2;
                    }
                }
            }

            if (null === $this->listener) {
                throw new \RuntimeException('Listener "SoftDeleteableListener" was not added to the EventManager!');
            }
        }

        return $this->listener;
    }

    protected function getDocumentManager()
    {
        if (null === $this->documentManager) {
            $getManager = \Closure::bind(function (BsonFilter $filter) {
                return $filter->dm;
            }, null, 'Doctrine\ODM\MongoDB\Query\Filter\BsonFilter');
            $this->documentManager = $getManager($this);
        }

        return $this->documentManager;
    }

    public function disableForDocument($class)
    {
        $this->disabled[$class] = true;
    }

    public function enableForDocument($class)
    {
        $this->disabled[$class] = false;
    }
}
