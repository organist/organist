<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mdekrijger
 * Date: 1/29/12
 * Time: 1:22 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Netvlies\PublishBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\DataEvent;
use Netvlies\PublishBundle\Entity\TargetRepository;
use Netvlies\PublishBundle\Entity\Deployment;
use Netvlies\PublishBundle\Form\ChoiceList\EnvironmentsType as EnvironmentChoice;
use Netvlies\PublishBundle\Form\DataTransformer\IdToEnvironment;


class EnvironmentType extends AbstractType
{

    private $em;

    public function __construct($em){
        $this->em = $em;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {

        $envChoice = new EnvironmentChoice($this->em);
        $builder
                ->add('environment', 'choice', array(
                        'label' => ' ',
                        'choice_list'=>$envChoice,
                        'required' => true,
                ))
                ->appendClientTransformer(new IdToEnvironment($this->em));;

    }

    public function getDefaultOptions(array $options)
    {
        $options['csrf_protection'] = false;

        return $options;
    }

    public function getName()
    {
        return 'netvlies_publishbundle_envtype';
    }

}
