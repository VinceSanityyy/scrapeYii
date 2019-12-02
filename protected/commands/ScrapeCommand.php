<?php

class ScrapeCommand extends CConsoleCommand
{

    private $connection;

    /**
     * Basic class setup
     */
    public function init() {
        $this->connection = Yii::app()->db;
    }

    public function fixJsonData(){

        $proxyIP = 'http://proxy.packetstream.io';

        //The port that the proxy is listening on.
        $proxyPort = '31112';

        //The username for authenticating with the proxy.
        $proxyUsername = 'snoopid';

        //The password for authenticating with the proxy.
        $proxyPassword = 'F49t22sjX971YLHY_country-Singapore';

        $properties = $this->connection->createCommand("SELECT pid ,json,property_type,property_name  FROM scraping.xp_guru_properties where property_type  <> 'HDB Apartment' AND json LIKE '%\r\n%'")->queryAll();
        foreach ($properties as $property){
            sleep(5);
            $search = $property['property_name'];
            $api_url = 'https://agentnet.propertyguru.com.sg/ex_xmlhttp_propertysearch?source=listing&type=&q='.urlencode($search);

            $ch = curl_init();
            $agents = array(
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
                'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1.9) Gecko/20100508 SeaMonkey/2.0.4',
                'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)',
                'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1'
            );

            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_USERAGENT, $agents[array_rand($agents)]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL , 1);
            curl_setopt($ch, CURLOPT_PROXY, $proxyIP );
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyUsername:$proxyPassword");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $raw_data = curl_exec($ch);
            $results = json_decode($raw_data);
            curl_close($ch);

            if ($results != NULL) {
                foreach ($results as $result) {
                    try {
                        $id= $result->id;
                        $results_properties = json_decode(json_encode($result));

                        $json_data = new StdClass;
                        foreach ($results_properties as  $key => $value){
                            $json_data->{$key} = $value;
                        }

                        $this->connection->createCommand()->update('xp_guru_properties', array(
                            'json'=> json_encode($result),
                        ), 'pid=:pid', array(':pid'=>$id));

                        print("fixed ". $id . "\r\n");

                    }catch (Throwable $e) {
                        print($e . "\r\n");
                    }
                    if (count($results) > 1){
                        sleep(5);
                    }
                }
            }


        }

        return;
    }
    public function actionGuruScrapeData(){
        $truncate = $this->connection->createCommand("SELECT * from xp_guru_pages where status = 0")->queryAll();

        if(count($truncate) == 0){
            $this->connection->createCommand("TRUNCATE TABLE xp_guru_pages")->execute();
            $this->connection->createCommand("TRUNCATE TABLE xp_guru_requests")->execute();
            $this->connection->createCommand("TRUNCATE TABLE xp_unprocessed_records")->execute();
        }

//        $this->fixJsonData();
        $this->scrapeCondo();
        $this->scrapeHDB();
        $this->scrapeCommercial();
        $this->exceuteUnProcessedRecords();
        $response_json = array(
            'status' => 'OK',
            'message' => 'Scraping Successfully'
        );
        echo json_encode($response_json);
        return;
    }



    public function scrapeCondo(){
        $alphabet = ['A','B','C','D','E','F','G','H','I'];
        foreach($alphabet as $letter){
            $url = 'https://www.propertyguru.com.sg/condo-directory/search/region/'.$letter;
            //create infinite loop
            while(1 === 1){
                sleep(10);
                $html = $this->qr_loadUrl($url);

                $dom = new DOMDocument();
                @$dom->loadHTML($html);
                $finder = new DomXPath($dom);

                // //initial list
                $classname = "name";
                $names = $finder->query("//*[contains(@itemprop, '$classname')]");

                if($names->length > 0){
                    $this->savePage($url);
                    print("success: ". $url. "\r\n");
                    break;
                }
                print("fail attempt! ". $url . "\r\n");
            }

            foreach($names as $name){
                $search = $name->nodeValue;
                if($this->skipped($search) == true){
                    continue;
                }
                $api_url = 'https://agentnet.propertyguru.com.sg/ex_xmlhttp_propertysearch?source=listing&type=&q='.urlencode($search);
                $this->updateData($api_url);
                $this->saveSearch($search,$api_url);
            }
            $this->updatePageStatus($url);

            $classname="pagination";
            $paginations= $finder->query("//*[contains(@class, '$classname')]//a/@href");
            $last_page = $this->getLastPage($paginations,1);

            for ($page=2; $page <= $last_page; $page++){
                $condo_paginate_page_url = 'https://www.propertyguru.com.sg/condo-directory/search/region/' . $letter .'/'.$page;

                if ($this->skipPage($condo_paginate_page_url) == true){
                    continue;
                }
                //create infinite loop
                while(1 === 1){
                    sleep(10);
                    $html = $this->qr_loadUrl($condo_paginate_page_url);

                    $dom = new DOMDocument();
                    @$dom->loadHTML($html);
                    $finder = new DomXPath($dom);

                    $classname = "name";
                    $names = $finder->query("//*[contains(@itemprop, '$classname')]");

                    if ($names->length > 0){
                        $this->savePage($condo_paginate_page_url);
                        print("success: ". $condo_paginate_page_url. "\r\n");
                        break;
                    }
                    print("fail attempt! ". $condo_paginate_page_url . "\r\n");
                }

                foreach($names as $name){
                    $search = $name->nodeValue;
//                    $search = preg_replace('/[^a-zA-Z0-9_ -]/s','',$search);
                    if($this->skipped($search) == true){
                        continue;
                    }
                    $api_url = 'https://agentnet.propertyguru.com.sg/ex_xmlhttp_propertysearch?source=listing&type=&q='.urlencode($search);
                    $this->updateData($api_url);
                    $this->saveSearch($search,$api_url);
                }
                $this->updatePageStatus($condo_paginate_page_url);
                
            }
            sleep(10);
        }
        return;
    }

    public function scrapeHDB(){
        
        //create infinite loop
        while(1 === 1){
            sleep(10);
            $url = 'https://www.propertyguru.com.sg/singapore-property-listing/hdb';
            $html = $this->qr_loadUrl($url);

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $finder = new DomXPath($dom);
            
            // //initial list
            $classname = "singapore-property-listing-hdb-page__search-section__search-items__item";
            $links = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]//a/@href");

            if($links->length > 0){
                print("success: ". $url. "\r\n");
                break;
            }
            print("fail attempt! " . $url . "\r\n");
        }


        foreach($links as $link){
            //create infinite loop
            $root_url = 'https://www.propertyguru.com.sg'. $link->nodeValue;
            if ($this->skipPage($root_url) == true){
                continue;
            }

            while(1 === 1){
                sleep(10);
                $html = $this->qr_loadUrl($root_url);

                $dom = new DOMDocument();
                @$dom->loadHTML($html);
                $finder = new DomXPath($dom);
                
                $classname = "singapore-property-listing-hdb-page__street-section__street-items__item";
                $streets = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]//a/@href");
                
                if($streets->length > 1){
                    $this->savePage($root_url);
                    print("success: ". $root_url. "\r\n");
                    break;
                }
                print("fail attempt! " . $root_url . "\r\n");
            }

            foreach($streets as $street){
                //create infinite loop
                $hdb_url = $street->nodeValue;
                $street_name = $street->ownerElement->nodeValue;

                if ($this->skipPage($hdb_url) == true){
                    continue;
                }

                while( 1 === 1){
                    sleep(30);
                    $html = $this->qr_loadUrl($hdb_url);

                    $dom = new DOMDocument();
                    @$dom->loadHTML($html);
                    $finder = new DomXPath($dom);
                    
                    $classname = "singapore-property-listing-hdb-page__hdb-street-blocks__items__content__name";
                    $blocks =  $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
                    
                    if($blocks->length > 0){
                        $this->savePage($hdb_url);
                        print("success: ". $hdb_url. "\r\n");
                        break;
                    }
                    print("fail attempt! " . $hdb_url . "\r\n");
                }
               
                foreach($blocks as $block){
                    $block_no = trim(str_replace('Blk','',$block->nodeValue));
                    $hdb_name = $block_no . ' ' . trim($street_name);

                    if ($this->skipped($hdb_name) == true){
                        continue;
                    }
                    $api_url = 'https://agentnet.propertyguru.com.sg/ex_xmlhttp_propertysearch?source=listing&type=&q='.urlencode($hdb_name);
                    $this->updateData($api_url);
                    $this->saveSearch($hdb_name,$api_url);
                }
                $this->updatePageStatus($hdb_url);
            }
            $this->updatePageStatus($root_url);
        }
        return;
    }

    public function scrapeCommercial(){
        $alphabet = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','X','Y','Z','0-9'];
        foreach ($alphabet as $letter){
            $url="https://www.commercialguru.com.sg/commercial-property-directory/name/". $letter ."?items_per_page=50";
            while(1==1){
                sleep(10);
                $html = $this->qr_loadUrl($url);
                $dom = new DOMDocument();
                @$dom->loadHTML($html);

                $finder = new DOMXPath($dom);
                $classname = "font14";
                $names = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]//b//a");

                if($names->length >0){
                    $this->savePage($url);
                    print("success: ". $url. "\r\n");
                    break;
                }
            }

            foreach ($names as $name){
                $search = $name->nodeValue;

                if ($this->skipped($search) == true){
                    continue;
                }

                $api_url = 'https://agentnet.propertyguru.com.sg/ex_xmlhttp_propertysearch?source=listing&type=&q='.urlencode($search);
                $this->updateData($api_url);
                $this->saveSearch($search,$api_url);
            }
            $this->updatePageStatus($url);

            $classname="pagination";
            $paginations= $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]//a/@href");
            $last_page = $this->getLastPage($paginations,3);

            for ($page=2; $page <= $last_page; $page++){
                $commercial_paginate_page_url = 'https://www.commercialguru.com.sg/commercial-property-directory/name/'.$letter.'/'. $page.'?items_per_page=50';
                if($this->skipPage($commercial_paginate_page_url) == true){
                    continue;
                }
                //create infinite loop
                while(1==1){
                    sleep(10);
                    $html = $this->qr_loadUrl($commercial_paginate_page_url);
                    $dom = new DOMDocument();
                    @$dom->loadHTML($html);

                    $finder = new DOMXPath($dom);
                    $classname = "font14";
                    $names = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]//b//a");

                    if($names->length >0){
                        $this->savePage($commercial_paginate_page_url);
                        print("success: ". $commercial_paginate_page_url. "\r\n");
                        break;
                    }
                }
                foreach ($names as $name){
                    $search = $name->nodeValue;

                    if ($this->skipped($search) == true){
                        continue;
                    }

                    $api_url = 'https://agentnet.propertyguru.com.sg/ex_xmlhttp_propertysearch?source=listing&type=&q='.urlencode($search);
                    $this->updateData($api_url);
                    $this->saveSearch($search,$api_url);
                }
                $this->updatePageStatus($commercial_paginate_page_url);
            }
        }
        return;

    }

    public function exceuteUnProcessedRecords(){
        $records = $this->connection->createCommand("SELECT * from xp_unprocessed_records")->queryAll();

        foreach ($records as $record){
            sleep(10);
            $url = $record['url'];
            print("processing ". $record['url']. "........ \r\n");

            $proxyIP = 'http://proxy.packetstream.io';

            //The port that the proxy is listening on.
            $proxyPort = '31112';

            //The username for authenticating with the proxy.
            $proxyUsername = 'snoopid';

            //The password for authenticating with the proxy.
            $proxyPassword = 'F49t22sjX971YLHY_country-Singapore';


            $ch = curl_init();
            $agents = array(
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
                'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1.9) Gecko/20100508 SeaMonkey/2.0.4',
                'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)',
                'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1'
            );

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $agents[array_rand($agents)]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL , 1);
            curl_setopt($ch, CURLOPT_PROXY, $proxyIP );
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyUsername:$proxyPassword");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $raw_data = curl_exec($ch);
            $data = json_decode($raw_data);
            curl_close($ch);

            try{
                if ($data != null){
                    $id = $data[0]->id;
                    $full_address = str_replace("'", "",$data[0]->streetnumber . ' ' . $data[0]->streetname);
                    $json_data = json_encode($data[0]);
                    $property_name = str_replace("'", "", $data[0]->name);
                    $property_type = str_replace("'", "", $data[0]->typeCodeDescription);
                    $loc_json = $this->getLocJson($id);
                    $loc_json_decoded = json_decode($loc_json);

                    $if_exists = $this->connection->createCommand("SELECT * FROM xp_guru_properties WHERE pid='$id'")->queryAll();
                    if (count($if_exists) > 0 ) {
                        try{
//                            $this->connection->createCommand("UPDATE xp_guru_properties set json='$json_data', property_name= '$property_name', property_type = '$property_type', full_address = '$full_address', loc_json='$loc_json' WHERE pid='$id'")->execute();
                            $this->connection->createCommand()->update('xp_guru_properties', array(
                                'json'=> $data,
                                'property_name' => $property_name,
                                'property_type'=> $property_type,
                                'full_address' => $full_address,
                                'loc_json' => $loc_json,
                            ), 'pid=:pid', array(':pid'=>$id));
                            print("updated \r\n");

                        }catch (Throwable $e){
                            print($e. "\r\n");
                        }

                    } else {
                        try{
                            $this->connection->createCommand()
                                ->insert(
                                    'xp_guru_properties',
                                    array(
                                        'pid' =>  $id,
                                        'json' => $json_data,
                                        'property_name' => $property_name,
                                        'property_type' => $property_type,
                                        'full_address'=> $full_address,
                                        'loc_json' => $loc_json
                                    )
                                );
                            print("inserted \r\n");
                        }catch (Throwable $e){
                            print($e. "\r\n");
                        }

                    }

                    if($loc_json == ''){
                        return;
                    }

                    if(is_array($loc_json_decoded) == false ){
                        sleep(1800);
                    }
                }

            }catch(Throwable $e){
                print($e. "\r\n");
            }finally{

            }


        }
    }

    public function getLastPage($paginations,$type ){
        $last_page = 0;
        //condo
        if($type == 1){
            foreach ($paginations as $pagination){
                $last_page = explode('/',$pagination->nodeValue)[5];
            }
            //listings
        }elseif($type == 2){
            foreach ($paginations as $pagination){
                $last_page = explode('/',$pagination->nodeValue)[3];
                $last_page = explode('?',$last_page)[0];
            }

            //commercial
        }elseif($type == 3){
            foreach ($paginations as $pagination){
                $last_page = explode('/',$pagination->nodeValue)[4];
            }
        }

        return $last_page;
    }

    public function saveSearch($search,$request){

        $tosearch = str_replace("'","",$search);
        $tosearch = preg_replace('/[^a-zA-Z0-9_ -]/s','',$tosearch);
        $torequest = str_replace("'","",$request);
        try{
            $exists = $this->connection->createCommand("SELECT * FROM xp_guru_requests WHERE search ='$tosearch'")->queryAll();
            if(!$exists){
                $this->connection->createCommand()
                    ->insert(
                        'xp_guru_requests',
                        array(
                            'search' =>  $tosearch,
                            'request' => $torequest
                        )
                    );
            }
        }catch (Throwable $e){
            print($e);
        }finally{
            return;
        }
    }
    public function savePage($url){
        try{
            $exists = $this->connection->createCommand("SELECT * FROM xp_guru_pages WHERE page ='$url'")->queryAll();
            if(!$exists){
                $this->connection->createCommand()
                    ->insert(
                        'xp_guru_pages',
                        array(
                            'page' =>  $url
                        )
                    );
            }
        }catch (Throwable $e){
            print($e);
        }finally{

        }
    }
    public function updatePageStatus($url){
        try{
            $exists = $this->connection->createCommand("SELECT * from xp_guru_pages where page= '$url'")->queryAll();
            if(count($exists)>0){
                $this->connection->createCommand("UPDATE xp_guru_pages SET status=1 WHERE page='$url'")->execute();
            }
        }catch (Throwable $e){
            print($e);
        }finally{

        }
    }

    public function skipPage($url){
        $skipped = $this->connection->createCommand("SELECT * from xp_guru_pages where page ='$url'")->queryAll();

        if(count($skipped) > 0){
            return true;
        }else{
            return false;
        }
    }

    public function skipped($search){
        $tosearch = str_replace("'","",$search);
        $tosearch = preg_replace('/[^a-zA-Z0-9_ -]/s','',$tosearch);
        $skipped = $this->connection->createCommand("SELECT * from xp_guru_requests where search ='$tosearch'")->queryAll();

        if(count($skipped) > 0){
            return true;
        }else{
            return false;
        }
    }
    public function updateData($url){
        $proxyIP = 'http://proxy.packetstream.io';

        //The port that the proxy is listening on.
        $proxyPort = '31112';

        //The username for authenticating with the proxy.
        $proxyUsername = 'snoopid';

        //The password for authenticating with the proxy.
        $proxyPassword = 'F49t22sjX971YLHY_country-Singapore';


        $ch = curl_init();
        $agents = array(
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
            'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1.9) Gecko/20100508 SeaMonkey/2.0.4',
            'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)',
            'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1'
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $agents[array_rand($agents)]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL , 1);
        curl_setopt($ch, CURLOPT_PROXY, $proxyIP );
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyUsername:$proxyPassword");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $raw_data = curl_exec($ch);
        $datas = json_decode($raw_data);
        curl_close($ch);

        try{
            if ($datas != NULL){
                foreach ($datas as $data){
                    $id = $data->id;
                    $full_address = str_replace("'", "",$data->streetnumber . ' ' . $data->streetname);
                    $property_name = str_replace("'", "", $data->name);
                    $property_type = str_replace("'", "", $data->typeCodeDescription);
                    $loc_json = $this->getLocJson($id);
                    $loc_json_decoded = json_decode($loc_json);

                    $if_exists = $this->connection->createCommand("SELECT * FROM xp_guru_properties WHERE pid='$id'")->queryAll();
                    if (count($if_exists) > 0 ) {
                        try{
                            $this->connection->createCommand()->update('xp_guru_properties', array(
                                'json'=> json_encode($data),
                                'property_name' => $property_name,
                                'property_type'=> $property_type,
                                'full_address' => $full_address,
                                'loc_json' => $loc_json,
                            ), 'pid=:pid', array(':pid'=>$id));

                            print("updated \r\n");
                        }catch (Throwable $e){
                            print($e. "\r\n");
                        }

                    } else {
                        try{
                            $this->connection->createCommand()
                                ->insert(
                                    'xp_guru_properties',
                                    array(
                                        'pid' =>  $id,
                                        'json' => json_encode($data),
                                        'property_name' => $property_name,
                                        'property_type' => $property_type,
                                        'full_address'=> $full_address,
                                        'loc_json' => $loc_json
                                    )
                                );
                            print("inserted \r\n");
                        }catch (Throwable $e){
                            print($e. "\r\n");
                        }

                    }

                    if($loc_json == ''){
                        return;
                    }

                    if(is_array($loc_json_decoded) == false ){
                        sleep(1800);
                    }
                }
            }else{
                try{
                    $this->connection->createCommand()
                        ->insert(
                            'xp_unprocessed_records',
                            array(
                                'url' => $url,
                            )
                        );
                    print("inserted \r\n");
                }catch (Throwable $e){
                    print($e. "\r\n");
                }
            }



        }catch(Throwable $e){
            print($e. "\r\n");
        }finally{

        }

    }

    function qr_loadUrl( $url ) {
        $proxyIP = 'http://proxy.packetstream.io';

        //The port that the proxy is listening on.
        $proxyPort = '31112';

        //The username for authenticating with the proxy.
        $proxyUsername = 'snoopid';

        //The password for authenticating with the proxy.
        $proxyPassword = 'F49t22sjX971YLHY_country-Singapore';

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

    public function getLocJson($pid)
    {
        sleep(10);
        $proxyIP = 'http://proxy.packetstream.io';

        //The port that the proxy is listening on.
        $proxyPort = '31112';

        //The username for authenticating with the proxy.
        $proxyUsername = 'snoopid';

        //The password for authenticating with the proxy.
        $proxyPassword = 'F49t22sjX971YLHY_country-Singapore';

        $url = 'https://agentnet.propertyguru.com.sg/ex_xmlhttp_propertysearch?property_id=' . $pid;
        $agents = array(
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
            'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1.9) Gecko/20100508 SeaMonkey/2.0.4',
            'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)',
            'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1'
        );
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
        $response_json = curl_exec($ch);
        curl_close($ch);

        return $response_json;

    }



}
