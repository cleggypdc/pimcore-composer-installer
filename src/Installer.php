<?php

namespace Cleggypdc\Pimcore\Composer;

use Composer\Script\Event;
use Composer\Util\Filesystem;

class Installer
{

    const PIMCORE_VENDOR_PATH = 'pimcore' . DIRECTORY_SEPARATOR . 'pimcore';

    protected $event;

    protected $filesystem;

    public function __construct(Event $event)
    {
        clearstatcache();
        $this->event = $event;
        $this->filesystem = new Filesystem();
        $this->writeLn("Running Pimcore Installer");
        $this->ensureFolderStructure();
        $this->cleanupPimcoreFiles();
    }

    protected function ensureFolderStructure()
    {
        $rootPath = $this->getDocumentRootPath();
        $pimcoreSrcPath = $this->getVendorFolderPath() . DIRECTORY_SEPARATOR . self::PIMCORE_VENDOR_PATH;

        // check paths that should exist in the webroot
        $movePaths = [
            'app', //The application configuration, templates and translations
            'src', // the project's PHP code (Services, Controllers EventListeners ..)
            'var', // Private generated files - not accessible via the web (cache, logs etc)
            'web' // public files - some from Pimcore
        ];

        foreach($movePaths as $path) {
            // app - is the application configuration, templates and translations
            // move if one does not exist
            $correctPath = $rootPath . DIRECTORY_SEPARATOR . $path;
            $srcPath = $pimcoreSrcPath . DIRECTORY_SEPARATOR . $path;

            $this->filesystem->ensureDirectoryExists($correctPath);

            if (!$this->filesystem->isSymlinkedDirectory($srcPath)){
                // copy the src directory if the correct path is empty OR is the web directory
                if ($this->filesystem->isDirEmpty($correctPath) || $path === 'web') {
                    $this->filesystem->copyThenRemove($srcPath, $correctPath);
                } else {
                    $this->filesystem->removeDirectory($srcPath);
                }
                symlink($correctPath, $srcPath);
            }
        }
        unset($srcPath, $correctPath, $path);

        $symlinkPaths = [
            'pimcore', // main pimcore directory
            'bin' // the bin directory for console apps
        ];

        foreach($symlinkPaths as $path) {
            //symlink the console directory
            $correctPath = $rootPath . DIRECTORY_SEPARATOR . $path;
            $srcPath = $pimcoreSrcPath . DIRECTORY_SEPARATOR . $path;
            if (!$this->filesystem->isSymlinkedDirectory($correctPath)) {
                symlink($srcPath, $correctPath);
            }
        }
        unset($srcPath, $correctPath, $path);

        // make autoload symlink
        $autoloadCorrectPath = $pimcoreSrcPath . DIRECTORY_SEPARATOR . 'vendor';
        $autoloadSrcPath = $this->getVendorFolderPath();

        if ($this->filesystem->isSymlinkedDirectory($autoloadCorrectPath)) {
            $this->filesystem->unlink($autoloadCorrectPath);
        }

        symlink($autoloadSrcPath, $autoloadCorrectPath);

        \Pimcore\Composer::parametersYmlCheck($rootPath);
    }

    protected function cleanupPimcoreFiles()
    {
        $pimcoreSrcPath = $this->getVendorFolderPath() . DIRECTORY_SEPARATOR . self::PIMCORE_VENDOR_PATH;

        //cleanup files
        $cleanupFiles = [
            '.github',
            '.travis',
            '.travis.yml',
            'update-scripts',
        ];

        foreach ($cleanupFiles as $file) {
            $path = $pimcoreSrcPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->filesystem->removeDirectory($path);
            } elseif (is_file($path)) {
                $this->filesystem->unlink($path);
            }
        }

        $installerFile = $this->getDocumentRootPath() . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'install.php';
        if (file_exists($installerFile)) {
            $this->filesystem->remove($installerFile);
        }

        \Pimcore\Composer::zendFrameworkOptimization($pimcoreSrcPath); //todo remove for pimcore 6.0
    }

    protected function getDocumentRootPath()
    {
        return realpath(getcwd() . DIRECTORY_SEPARATOR . ($this->getConfig()->get('document-root-path') ?: 'www'));
    }

    protected function getVendorFolderPath()
    {
        return realpath($this->getConfig()->get('vendor-dir'));
    }

    protected function getConfig()
    {
        return $this->event->getComposer()->getConfig();
    }

    protected function writeLn($string='')
    {
        echo $string . PHP_EOL;
    }

    public static function install(Event $event)
    {
        $self = new self($event);
    }

}
