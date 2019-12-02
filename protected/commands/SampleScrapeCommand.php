<?php

class SampleScrapeCommand extends CConsoleCommand
{

    private $connection;

    /**
     * Basic class setup
     */
    public function init() {
        $this->connection = Yii::app()->db;
    }


    public function actionScrapeData()
    {
        $url = 'https://www.propertyguru.com.sg/condo-directory/search/region/H';
        //create infinite loop

        sleep(10);
        $html = $this->qr_loadUrl($url);

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $finder = new DomXPath($dom);

        // //initial list
        $classname = "name";
        $names = $finder->query("//*[contains(@itemprop, '$classname')]");

        foreach($names as $name) {
            $search = $name->nodeValue;
            print($search. "\r\n");
        }

        return;
    }

    function qr_loadUrl( $url ) {
        $proxyIP = 'http://us-wa.proxymesh.com';

        //The port that the proxy is listening on.
        $proxyPort = '31280';

        //The username for authenticating with the proxy.
        $proxyUsername = 'awesomepatrik';

        //The password for authenticating with the proxy.
        $proxyPassword = 'patal2018';

        $agents = array(
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
            'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1.9) Gecko/20100508 SeaMonkey/2.0.4',
            'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)',
            'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1'
        );


        if(is_callable( 'curl_init' )) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $agents[array_rand($agents)]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL , 1);
            curl_setopt($ch, CURLOPT_PROXY, $proxyIP );
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyUsername:$proxyPassword");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($ch);
            curl_close($ch);

        }
        if( empty($data) || !is_callable('curl_init') ) {
            $opts = array('http'=>array('header' => 'Connection: close'));
            $context = stream_context_create($opts);
            $headers = get_headers($url);
            $httpcode = substr($headers[0], 9, 3);
            if( $httpcode == '200' )
                $data = file_get_contents($url, false, $context);
            else{
                $data = '{"div":"Error ' . $httpcode . ': Invalid Url<br />"}';
            }
        }
        return $data;
    }

}
