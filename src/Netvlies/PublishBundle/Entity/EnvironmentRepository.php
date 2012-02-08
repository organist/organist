<?php

namespace Netvlies\PublishBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * EnvironmentRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class EnvironmentRepository extends EntityRepository
{

    public function getByTypeAndHost($type, $host){

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery('
            SELECT e FROM Netvlies\PublishBundle\Entity\Environment e
            WHERE e.type = :type
            AND e.hostname = :host
        ');

        $query->setParameter('type', $type);
        $query->setParameter('host', $host);

        return $query->getResult();
    }


    /**
     * Gets an array of all environment objects ordered by O, T, A, P
     * @return array
     */
    public function getOrderedByTypeAndHost(){

        $rsm = new ResultSetMapping;
        $rsm->addEntityResult('NetvliesPublishBundle:Environment', 'e');
        $rsm->addFieldResult('e', 'id', 'id');
        $rsm->addFieldResult('e', 'type', 'type');
        $rsm->addFieldResult('e', 'hostname', 'hostname');

        $query = $this->getEntityManager()->createNativeQuery("
            SELECT e.* FROM Environment e
            ORDER BY FIELD(e.type, 'O', 'T', 'A', 'P')
        ", $rsm);

        $result = $query->getResult();

        return $result;
    }


}
