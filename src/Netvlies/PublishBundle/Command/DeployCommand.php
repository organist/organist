<?php
/**
 * Created by JetBrains PhpStorm.
 * User: markri
 * Date: 13-1-12
 * Time: 9:52
 * To change this template use File | Settings | File Templates.
 */

namespace Netvlies\PublishBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Netvlies\PublishBundle\Entity\DeploymentLog;
use Netvlies\PublishBundle\Controller\ConsoleController;

class DeployCommand extends ContainerAwareCommand
{

     protected function configure()
     {
         // params:
             // target
             // reference with head as default
            // use internal console without output (if possible)
         // We need to make an alternative interpretation of anyterm.js to connect to internal hosted anyterm
         // JS wont run on command line :-)
         // or we could use tail -f or something like that in combination with curl



         $this
             ->setName('publish:deploy')
             ->setDescription('Display settings needed for target. Needs targetid')
             ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Target id')
             ->addOption('pd', null, InputOption::VALUE_OPTIONAL, 'Primary domain')
             ->addOption('branch', null, InputOption::VALUE_OPTIONAL, 'Deployment id', 'refs/heads/master')
         ;
     }

     protected function execute(InputInterface $input, OutputInterface $output)
     {
         $id = $input->getOption('id');
         $pd = $input->getOption('pd');
         $branch = $input->getOption('branch');

         if(empty($id) && empty($pd)){
             throw new Exception('primary domain or id is required');
             return;
         }

         $em = $this->getContainer()->get('doctrine')->getEntityManager();
         /**
          * @var \Netvlies\PublishBundle\Entity\Target $target
          */
         if(!empty($id)){
             $target = $em->getRepository('NetvliesPublishBundle:Deployment')->findOneByid($id);
         }
         else{
             $target = $em->getRepository('NetvliesPublishBundle:Deployment')->findOneByPrimaryDomain($pd);
         }

         $app = $target->getApplication();
         $app->setBaseRepositoriesPath($this->getContainer()->getParameter('repositorypath'));
         $branches = $app->getRemoteBranches();

         if(!in_array($branch, $branches)){
            throw new \Exception('No such branch '.$branch);
         }

         $reference = array_search($branch, $branches);

         $console = new  ConsoleController();
         $params = $console->getSettings($this->getContainer(), $target, $reference);

        foreach($params as $key=>$value){
            echo $key.'='.$value."\n";
        }
     }
}