<?php

namespace Modera\BackendModuleBundle\Controller;

use Neton\DirectBundle\Annotation\Remote;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Modera\Module\Repository\ModuleRepository;

class DefaultController extends Controller
{
    /**
     * @var ModuleRepository
     */
    private $moduleRepository;

    /**
     * @return \Modera\Module\Repository\ModuleRepository
     */
    private function getModuleRepository()
    {
        if (!$this->moduleRepository) {
            $workingDir = dirname($this->container->get('kernel')->getRootdir());
            $this->moduleRepository = new ModuleRepository($workingDir);
        }

        return $this->moduleRepository;
    }

    /**
     * @return int
     */
    private function getModuleClientPort()
    {
        return 8021; //TODO: move to config
    }

    /**
     * @return string
     */
    private function getDefaultLogo()
    {
        return '/bundles/moderabackendmodule/images/default.png';
    }

    /**
     * @param array $versions
     * @return \Packagist\Api\Result\Package\Version
     */
    private function getPackageLatestVersion(array $versions)
    {
        ksort($versions);
        return end($versions);
    }

    /**
     * @param $name
     * @return array|null
     */
    private function getModuleInfo($name, $extended = false)
    {
        $package = $this->getModuleRepository()->getPackage($name);
        if (!$package) {
            return null;
        }

        $latest = $this->getPackageLatestVersion($package->getVersions());
        $installed = $this->getModuleRepository()->getInstalledByName($latest->getName());
        $lastVersion = $latest->getVersion();
        $currentVersion = null;
        $updateAvailable = false;

        if ($installed) {
            $currentVersion = $this->getModuleRepository()->formatVersion($installed);
            if ($installed->getPrettyVersion() !== $lastVersion) {
                $updateAvailable = true;
            }
        }

        $result = array(
            'id'              => $latest->getName(),
            'logo'            => $this->getDefaultLogo(),
            'name'            => $latest->getName(),
            'description'     => $latest->getDescription(),
            'license'         => $latest->getLicense(),
            'lastVersion'     => $lastVersion,
            'currentVersion'  => $currentVersion,
            'installed'       => $installed ? true : false,
            'updateAvailable' => $updateAvailable,
        );

        if ($extended) {
            $extra = $latest->getExtra();
            $extra = $extra['modera-module'];

            $screenshots = array();
            if (isset($extra['screenshots']) && is_array($extra['screenshots'])) {
                foreach($extra['screenshots'] as $key => $screenshot) {
                    if (!is_array($screenshot)) {
                        $screenshot = array(
                            'thumbnail' => $screenshot,
                            'src'       => $screenshot,
                        );
                    }
                    $screenshots[] = $screenshot;
                }
            }

            $longDescription = '';
            if (isset($extra['description'])) {
                $longDescription = $extra['description'];
                if (is_array($longDescription)) {
                    $longDescription = implode("\n", $longDescription);
                }
                $longDescription = strip_tags($longDescription);
                $longDescription = str_replace("\n", "<br />", $longDescription);
            }

            $authors = array();
            foreach ($latest->getAuthors() as $author) {
                $authors[] = $author->getName();
            }
            $createdAt = new \DateTime($latest->getTime());

            $result += array(
                'authors'         => count($authors) ? implode(", ", $authors) : null,
                'createdAt'       => $createdAt->format(\DateTime::RFC1123),
                'longDescription' => $longDescription,
                'screenshots'     => $screenshots,
            );
        }

        return $result;
    }

    /**
     * @Remote
     *
     * @param array $params
     */
    public function getInstalledModulesAction(array $params)
    {
        $result = array();
        $packages = $this->getModuleRepository()->getInstalled();
        foreach ($packages as $package) {
            $info = $this->getModuleInfo($package->getName());
            if ($info) {
                $result[] = $info;
            }
        }

        return $result;
    }

    /**
     * @Remote
     *
     * @param array $params
     */
    public function getAvailableModulesAction(array $params)
    {
        $result = array();
        $data = $this->getModuleRepository()->getAvailable();
        foreach ($data as $name) {
            $info = $this->getModuleInfo($name);
            if ($info) {
                $result[] = $info;
            }
        }

        return $result;
    }

    /**
     * @Remote
     *
     * @param array $params
     */
    public function getModuleDetailsAction(array $params)
    {
        return $this->getModuleInfo($params['id'], true);
    }

    /**
     * @param $url
     * @return array
     */
    private function remoteUrls($url)
    {
        $port = $this->getModuleClientPort();
        return array(
            'call'   => $url . ':' . $port . '/call',
            'status' => $url . ':' . $port . '/status',
        );
    }

    /**
     * @Remote
     *
     * @param array $params
     */
    public function requireAction(array $params)
    {
        $response = array(
            'success' => false,
            'params'  => array(),
            'urls'    => $this->remoteUrls($params['url']),
        );

        $package = $this->getModuleRepository()->getPackage($params['id']);
        if ($package) {
            $latest = $this->getPackageLatestVersion($package->getVersions());
            $response['success'] = true;
            $response['params'] = array(
                'method'  => 'require',
                'name'    => $latest->getName(),
                'version' => $latest->getVersion(),
            );
        }

        return $response;
    }

    /**
     * @Remote
     *
     * @param array $params
     */
    public function removeAction(array $params)
    {
        $response = array(
            'success' => true,
            'params'  => array(
                'method'  => 'remove',
                'name'    => $params['id'],
            ),
            'urls'    => $this->remoteUrls($params['url']),
        );

        return $response;
    }

    /**
     * @Remote
     *
     * @param array $params
     */
    public function checkAction(array $params)
    {
        $response = array(
            'success'        => true,
            'updated_models' => array(
                'modera.backend_module_bundle.module' => [$params['id']],
            ),
        );

        return $response;
    }
}