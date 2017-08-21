<?php


namespace Bonnier\SystemText\Console\Commands;


use Bonnier\SystemText\SystemTextProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FetchTranslationsCommand extends Command
{
    private $sitemanager_url;
    private $translation_endpoint;
    private $transPath;
    private $client;
    private $force;

    protected $name = 'bonnier:fetch';

    protected $signature = 'bonnier:fetch {--F|force}';

    protected $description = 'Fetch translations from external service';

    public function __construct()
    {
        parent::__construct();
        $this->sitemanager_url = config('services.systemtext.sitemanager_url');
        $this->translation_endpoint = config('services.systemtext.translation_endpoint');
        $this->client = new Client();
    }

    public function handle()
    {
        $this->info('Fetching translations');
        $this->force = $this->option('force');
        $this->transPath = SystemTextProvider::getTranslationPath();
        $apps = $this->getApps();
        $brands = $this->getBrands();
        $urls = $this->getUrls($apps, $brands);
        $this->info('----------------------------');
        $translations = $this->getTranslations($urls);
        $this->saveTranslations($translations);
        $this->info('Complete!');
    }

    /**
     * Get all apps from sitemanager
     * Quits script if no apps are found
     *
     * @return array
     */
    protected function getApps()
    {
        try {
            $response = $this->client->get($this->sitemanager_url . '/api/v1/apps');
        } catch(ClientException $e) {
            $this->error('Could not retrieve apps: '.$e->getResponse()->getStatusCode());
            exit;
        }

        $result = \json_decode($response->getBody());
        if($result) {
            $this->info(sprintf('Found %s apps', count($result)));
            return $result;
        }

        $this->error('No apps found');
        exit;
    }

    /**
     * Get all brands from sitemanager
     * Quits script if no brands are found
     *
     * @return array
     */
    protected function getBrands()
    {
        try {
            $response = $this->client->get($this->sitemanager_url . '/api/v1/brands');
        } catch(ClientException $e) {
            $this->error(sprintf('Could not retrieve brands: %s', $e->getResponse()->getStatusCode()));
            exit;
        }

        $result = \json_decode($response->getBody());
        if(isset($result->data)) {
            $this->info(sprintf('Found %s brands', count($result->data)));
            return $result->data;
        }

        $this->error('No brands found');
        exit;
    }

    /**
     * Convert apps and brands to array of url strings
     *
     * @param array $apps
     * @param array $brands
     * @return array
     */
    protected function getUrls($apps, $brands)
    {
        $urls = [];

        foreach($apps as $app)
        {
            foreach($brands as $brand)
            {
                $urls[] = strtolower($app->app_code.'/'.$brand->brand_code);
            }
        }

        $this->info(sprintf("Generated %s urls", count($urls)));

        return $urls;
    }

    /**
     * Pulls data from
     *
     * @param array $urls
     */
    protected function getTranslations($urls)
    {
        if($this->force || $this->confirm('Do you want to fetch and overwrite the translations?'))
        {
            $urlCount = count($urls);
            $urlBar = $this->output->createProgressBar($urlCount);
            $this->info(sprintf('Fetching translations from %s urls...', $urlCount));
            $translations = [];
            foreach($urls as $url) {
                $data = $this->fetchData($url);
                if($data) {
                    $translations[$url] = $data;
                }
                $urlBar->advance();
            }
            $urlBar->finish();
            echo PHP_EOL;
            return $translations;
        } else {
            $this->alert('Operation cancelled');
            exit;
        }
    }

    protected function fetchData($url)
    {
        $uri = $this->translation_endpoint.'/'.$url;
        try {
            $response = $this->client->get($uri);
        } catch(ClientException $e) {
            if($e->getResponse()->getStatusCode() !== 404) {
                $this->error(sprintf('Could not recieve translations from %s: %s', $uri, $e->getResponse()->getStatusCode()));
            }
            return null;
        }

        $result = \json_decode($response->getBody());
        if($result)
        {
            return $result;
        }

        return null;
    }

    protected function saveTranslations($translations)
    {
        $this->info('Saving translations');
        $saveBar = $this->output->createProgressBar(count($translations));
        foreach($translations as $appBrand => $data) {
            $trans = $this->convertTranslationObjects($data);

            foreach($trans as $lang => $translations) {
                if(!$this->writeTranslations($lang, $appBrand, $translations)) {
                    $this->error(sprintf('Error writing translation for %s in %s', $lang, $appBrand));
                }
            }
            $saveBar->advance();
        }
        $saveBar->finish();
        echo PHP_EOL;
    }

    protected function convertTranslationObjects($data)
    {
        $trans = [];
        foreach($data as $translationKey => $strings) {
            foreach($strings as $lang => $string) {
                if(!isset($trans[$lang])) {
                    $trans[$lang] = [
                        $translationKey => $string
                    ];
                } else {
                    $trans[$lang][$translationKey] = $string;
                }
            }
        }

        return $trans;
    }

    protected function writeTranslations($lang, $subfolder, $translations)
    {
        $structure = $lang.DIRECTORY_SEPARATOR.$subfolder;
        $path = $this->transPath.DIRECTORY_SEPARATOR.$structure;
        $filepath = $path.DIRECTORY_SEPARATOR."messages.php";

        if(!File::exists($path))
        {
            if(!File::makeDirectory($path, 0770, true))
            {
                $this->error(sprintf('Could not make directory \'%s\'', $structure));
            }
        }

        $fileContent = "<?php".PHP_EOL;
        $fileContent .= PHP_EOL;
        $fileContent .= "return [".PHP_EOL;
        foreach($translations as $key => $value) {
            $fileContent .= "    '".$key."' => '".$value."',".PHP_EOL;
        }
        $fileContent .= "];".PHP_EOL;

        if(File::exists($filepath)) {
            File::delete($filepath);
        }
        return File::put($filepath, $fileContent);
    }
}