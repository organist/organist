<?php
/**
 * @author: M. de Krijger
 * Creation date: 25-1-12
 */


namespace Netvlies\PublishBundle\Services;


use Netvlies\PublishBundle\Entity\Application;

class GitBitbucket
{

    protected $basePath;
    protected $user;
    protected $password;
    protected $owner;
    protected $cachectl;

    /**
     * @var \Netvlies\PublishBundle\Entity\Application $app
     */
    protected $app;


    /**
     * @param $repositoryBasePath
     * @param $user
     * @param $password
     * @param $owner
     */

    /**
     * @param $container
     */
    public function __construct($container){

        $this->basePath = $container->getParameter('repositorypath');
        $this->user = $container->getParameter('bitbucketuser');
        $this->password = $container->getParameter('bitbucketpassword');
        $this->owner = $container->getParameter('bitbucketrepoowner');

        $frontendOptions = array(
           'lifetime' => 7200, // cache lifetime of 2 hours
           'automatic_serialization' => true
        );

        $backendOptions = array(
            'cache_dir' => $container->getParameter('kernel.root_dir').'/cache/'.$container->getParameter('kernel.environment') // Directory where to put the cache files
        );

        // getting a Zend_Cache_Core object
        $this->cachectl = \Zend_Cache::factory('Core',
                                     'File',
                                     $frontendOptions,
                                     $backendOptions);

        if(!file_exists($this->basePath)){
            throw new \Exception('Repository base path doesnt exist, please change your config.yml');
        }
    }


    /**
     * @throws \Exception
     */
    protected function checkApp(){
        if(is_null($this->app)){
            throw new \Exception('Application needs to be set before any other methods can be used');
        }



    }

    /**
     * @param \Netvlies\PublishBundle\Entity\Application $app
     */
    public function setApplication(Application $app){
        $this->app = $app;
    }

    /**
     * @todo this method might return a string with "fatal: remote end hung up unexpectedly" When no valid key is found
     * So we should build some way to validate the keys and throw an exception for that
     * @return array
     */
    public function getRemoteBranches($fresh=false){

        $this->checkApp();

        if( ($result = $this->cachectl->load('remotebranches_'.$this->app->getId())) !== false && $fresh == false) {
            return $result;
        }

        if(!file_exists($this->getAbsolutePath())){
            throw new \Exception('Repository path doesnt exist '.$this->getAbsolutePath());
        }

        $path = $this->getAbsolutePath();
        $command = 'cd '.$path.'; git ls-remote 2>&1';
        $output = shell_exec($command);

        $regex = '/\n(.{40})\s*(.*)$/im';
        $matches = array();
        $numberfound = preg_match_all($regex, $output, $matches);

        if($numberfound == 0){
            return array();
        }

        $refs = $matches[1];
        $branchnames = $matches[2];

        $return = array_combine($refs, $branchnames);

        $this->cachectl->save($return, 'remotebranches_'.$this->app->getId());

        return $return;
    }



    /**
     * @param $reference Latest reference to calculate from.
     * @return array|\false|mixed
     */
	public function getLastChangesets($reference='')
    {
        $this->checkApp();

        if( ($result = $this->cachectl->load('changesets_'.$this->app->getId().$reference)) !== false ) {
            return $result;
        }

        // Reference is bound to application by selecting it in a form
        // so we can use the internal getter
        //$reference = $this->app->getReferenceToDeploy();

        // @see documentation about following curl command at http://confluence.atlassian.com/display/BITBUCKET/Changesets
        $startRef = empty($reference)?'':'&start='.$reference;
        $ch = curl_init();
        $url = 'https://api.bitbucket.org/1.0/repositories/'.$this->owner.'/'.$this->app->getRepoKey().'/changesets/?&limit=15'.$startRef;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        curl_close($ch);

        $assoc = json_decode($json, true);
        $changesets = $assoc['changesets'];
        $topdownchangesets = array_reverse($changesets);
        $allowednodes = $topdownchangesets[0]['parents'];
        $branchesets = array();
        $branchesets[] = $topdownchangesets[0];

        foreach($topdownchangesets as $changeset){
            if(in_array($changeset['node'], $allowednodes)){
                $branchesets[] = $changeset;
                $allowednodes = array_merge($allowednodes, $changeset['parents']);
            }
        }

        $this->cachectl->save($branchesets, 'changesets_'.$this->app->getId().$reference);

        return $branchesets;
	}

    /**
     * @return string
     */
    public function getBitbucketChangesetURL(){
        $this->checkApp();
        return 'https://bitbucket.org/'.$this->owner.'/'.$this->app->getRepoKey().'/changesets';
    }

    /**
     * @return string
     */
    public function getAbsolutePath(){
        return $this->basePath . '/' . $this->app->getRepoKey();
    }

    /**
     * @return string Absolute path of the build.xml file
     */
    public function getBuildFile(){
        return $this->getAbsolutePath().'/build.xml';
    }
}
