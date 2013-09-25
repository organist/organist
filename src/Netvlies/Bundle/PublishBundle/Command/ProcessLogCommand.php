<?php
/**
 * This file is part of Organist
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author: markri <mdekrijger@netvlies.nl>
 */

namespace Netvlies\Bundle\PublishBundle\Command;

use Netvlies\Bundle\PublishBundle\Versioning\ReferenceInterface;
use Netvlies\Bundle\PublishBundle\Versioning\VersioningInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Netvlies\Bundle\PublishBundle\Entity\CommandLog;

class ProcessLogCommand extends ContainerAwareCommand
{

     protected function configure()
     {
         $this
             ->setName('publish:processlog')
             ->setDescription('Processes log entry of given command.')
             ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Execution UID')
             ->addOption('exitcode', null, InputOption::VALUE_OPTIONAL, 'Exit code')
         ;
     }

     protected function execute(InputInterface $input, OutputInterface $output)
     {
         $id = $input->getOption('id');
         $exitcode = $input->getOption('exitcode');
         $logDir = dirname(dirname(dirname(dirname(dirname(__DIR__))))).'/app/logs/scripts';

         $ids = array();

         // Also include log files older than 24 hours. They're probably not going to finish anyway. So clear them
         $iterator = new \DirectoryIterator($logDir);
         foreach ($iterator as $fileinfo) {
             if ($fileinfo->isFile() && $fileinfo->getExtension() == 'log') {
                 if(time() - $fileinfo->getMTime() > 60 * 60 * 24 * 1){
                     $ids[] = $fileinfo->getBasename('.log');
                 }
             }
         }

         if(!empty($id)){
             $ids[] = $id;
         }

         $em = $this->getContainer()->get('doctrine')->getManager();


         foreach($ids as $id){
             /**
              * @var \Netvlies\Bundle\PublishBundle\Entity\CommandLog $commandLog
              */
             $commandLog = $em->getRepository('NetvliesPublishBundle:CommandLog')->find($id);

             if(is_null($commandLog)){
                 echo "Warning: No log entry available to update, remove log manually...";
                 continue;
             }

             $logfile = $logDir.'/'.$id.'.log';
             $commandLog->setDatetimeEnd(new \DateTime());
             $commandLog->setLog(file_get_contents($logfile));
             $commandLog->setExitCode($exitcode);

             $em->persist($commandLog);
             $em->flush();
             unlink($logfile);

             /**
              * @var \Netvlies\Bundle\PublishBundle\Entity\Target $target
              */
             $target = $commandLog->getTarget();

             if(empty($target)){
                 continue;
             }

             $command = 'ssh '.$target->getUsername().'@'.$target->getEnvironment()->getHostname().' cat '.$target->getApproot().'/REVISION || true';
             $revision = trim(shell_exec($command));

             if(!empty($revision)){
                 $target->setLastDeployedRevision($revision);

                 /**
                  * @var VersioningInterface $versioningService
                  */
                 $versioningService = $this->getContainer()->get($target->getApplication()->getScmService());

                 // Find tag
                 $tags = $versioningService->getTags($target->getApplication());

                 foreach($tags as $reference){
                     /**
                      * @var ReferenceInterface $reference
                      */
                     if($reference->getReference()!=$revision){
                         continue;
                     }

                     $target->setLastDeployedTag($reference->getName());
                     break;
                 }

                 // Find branch
                 $branches = $versioningService->getBranches($target->getApplication());
                 foreach($branches as $reference){
                     /**
                      * @var ReferenceInterface $reference
                      */
                     if($reference->getReference()!=$revision){
                         continue;
                     }

                     $target->setLastDeployedBranch($reference->getName());
                     break;
                 }

                 $em->persist($target);
                 $em->flush();
             }
         }
     }
}