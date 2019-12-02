<?php

class Scrape99Command extends CConsoleCommand
{

    /**
     * Defines the help command which simply outputs how to use this module from the
     * command line
     */


    private $connection;
    private $host;

    /**
     * Basic class setup
     */
    public function init() {
        $this->connection = Yii::app()->db;
        // $this->host = 'https://payment.propnex.net/guru-scraper';
       $this->host = 'http://locahost/yii-test';
    }

    public function actionSampleExec(){
        print("test");
    }
    public function actionFloorPlanScrape()
    {
        ini_set('max_execution_time', 0);
        $alphabet = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','%23');

        foreach ($alphabet as $letter){
            $root_page_url = 'https://www.99.co/singapore/condos-apartments?alphabet='.$letter;
            $parent_html = $this->qr_loadUrl($root_page_url);

            $dom = new DOMDocument();
            while(1 === 1){
                sleep(10);
                @$dom->loadHTML($parent_html);
                $xpath = new DOMXPath($dom);

                $classname = 'CondoDirectory__development__3gdS_';
                $links = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                if ($links->length >0){
                    print("success :". $root_page_url . "\r\n");
                    break;
                }
                print("fail attempt :". $root_page_url. "\r\n");

            }

            foreach($links as $link){
                $url = 'https://www.99.co'.$link->attributes[0]->nodeValue;
                $title = $link->childNodes[0]->nodeValue;

                print ($url. "\r\n");
                try{
                    $skip = $this->connection->createCommand("SELECT * from xp_pn_99co_projects where name = '$title'")->queryAll();
                    if($skip){
                        continue;
                    }
                    $this->scrapeItem($url);
                    sleep(10);
                }catch (Throwable $e){
                    print($e. "\r\n");
                }
            }


            $pagination_items = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' Pagination__PaginationList__2femE ')]//li");
            $last_page = 1;
            if($pagination_items->length > 2){
                $last_page =  $pagination_items[$pagination_items->length -2]->nodeValue;
            }else if($pagination_items->length == 2){
                $last_page = 2;
            }else if($pagination_items->length == 1){
                $last_page = 1;
            }

            for($i=2; $i <= $last_page; $i++){
                $next_pagination_url = $root_page_url .'&page='.$i;
                $pagination_html = $this->qr_loadUrl($next_pagination_url);

                while (1 === 1){
                    sleep(10);
                    $dom = new DOMDocument();
                    @$dom->loadHTML($pagination_html);
                    $xpath = new DOMXPath($dom);

                    $classname = 'CondoDirectory__development__3gdS_';
                    $links = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                    if($links->length > 0){
                        print("success :". $next_pagination_url. "\r\n");
                        break;
                    }
                    print("fail attempt :". $next_pagination_url. "\r\n");
                }


                foreach($links as $link){
                    $url = 'https://www.99.co'.$link->attributes[0]->nodeValue;
                    $title = $link->childNodes[0]->nodeValue;
                    print ($url. "\r\n");
                    try{
                        $skip = $this->connection->createCommand("SELECT * from xp_pn_99co_projects where name = '$title'")->queryAll();
                        if($skip){
                            continue;
                        }
                        $this->scrapeItem($url);
                        sleep(10);
                    }catch (Throwable $e){
                        print($e. "\r\n");
                    }


                }
                sleep(10);
            }


        }

        return;

    }

    public function scrapeItem($url){
        try{
            ini_set('max_execution_time', 0);

            $html =$this->qr_loadUrl($url);
            if(!preg_match('/(?<=window\.\_\_data\=)(\{.+?\}\;)/', $html, $matches)){
                return;
            }

            if(!substr($matches[0], 0, -1)){
                return;
            }

            $match = substr($matches[0], 0, -1);
            $match = preg_replace('/(function)\(\w(\,\w)?\)\{.*?\}/', '0', $match);
            $decoded_data = json_decode($match);



            $rent = [];
            if(isset($decoded_data->development->cluster_data->listings_summary_data->rent->rows)){
                $listings_summary_rent = $decoded_data->development->cluster_data->listings_summary_data->rent->rows;
                foreach($listings_summary_rent as $items){
                    $initial_item = new StdClass();
                    $count =1;
                    foreach ($items as $item){
                        if($count ==1){
                            $initial_item->bedroom_type = $item->title;
                        }elseif($count == 2){
                            $initial_item->price_range = $item->title;
                        }elseif($count ==3){
                            $initial_item->no_of_listings = $item->title;
                        }
                        $count = $count +1;
                    }
                    $rent[] = $initial_item;
                }
            }

            $sale = [];
            if(isset($decoded_data->development->cluster_data->listings_summary_data->sale->rows)) {
                $listings_summary_sale = $decoded_data->development->cluster_data->listings_summary_data->sale->rows;
                foreach($listings_summary_sale as $items){
                    $initial_item = new StdClass();
                    $count =1;
                    foreach ($items as $item){
                        if($count ==1){
                            $initial_item->bedroom_type = $item->title;
                        }elseif($count == 2){
                            $initial_item->price_range = $item->title;
                        }elseif($count ==3){
                            $initial_item->no_of_listings = $item->title;
                        }
                        $count = $count +1;
                    }
                    $sale[] = $initial_item;
                }
            }

            if(!isset($decoded_data->development->cluster_data->floor_plan_data)){
                return;
            }

            $unit_config_items = $decoded_data->development->cluster_data->floor_plan_data->unit_config_items;
            $floor_plans = json_decode(json_encode($decoded_data->development->cluster_data->floor_plan_data->floor_plan_map));

            $floor_plans_data =[];
            foreach ($floor_plans as $key => $value){

                $floor_plan_object = $this->getFloorPlanObject($unit_config_items,$key);
                $bathroom = $floor_plan_object != '' ? explode('-',$floor_plan_object->subtitle)[1]: 0;
                $value->bathroom = (int) trim(str_replace('Bath', '',$bathroom));
                $value->area = $floor_plan_object != '' ? $floor_plan_object->title : '';
                
                $photo_url = $value->photos[0]->url;
                $photo_url = str_replace(['height=600','quality=70'],['height=1200','quality=100'],$photo_url);
                $photo_title = $value->photos[0]->title;

                unset($value->photos);
                $floor_plan_path = realpath(Yii::app()->basePath . '/../floorplans');


                if($photo_title != 'Not Available'){
                    try{

                        $value->title = $photo_title;
                        $value->photo = $photo_url;

                        $directory = $floor_plan_path. '/'. $decoded_data->development->cluster_data->title;
                        @mkdir($directory);
                        $this->savePhoto($photo_url,$directory.'/temp.jpg');
                        $image = @imagecreatefromjpeg($directory.'/temp.jpg');
                        if($image && imagefilter($image,IMG_FILTER_CONTRAST,-20)){
                            
                            imagejpeg($image, $directory. '/'.$photo_title.'.jpg');
                            imagedestroy($image);

                            $value->processed_photo = $this->host . '/floorplans/'.$decoded_data->development->cluster_data->title.'/'.$photo_title.'.jpg';
                            $floor_plans_data[] = $value;
                        }

                    }catch (Throwable $e){
                        print($e . "\r\n");

                    }finally{

                    }
                }

            }


            $subtitle =  $decoded_data->development->cluster_data->subtitle;
            $result = array(
                "name" => $decoded_data->development->cluster_data->title,
                "address" => explode("-",$subtitle)[1],
                "district" => explode("-",$subtitle)[0],
                "property_type" => explode("-",$subtitle)[2],
                "completed_at" => $decoded_data->development->cluster_data->completed_at,
                "tenure" => $decoded_data->development->cluster_data->tenure,
                "rental_yield" => $decoded_data->development->cluster_data->rental_yield,
                "coordinates" =>$decoded_data->development->cluster_data->coordinates,
                "project_size" => $decoded_data->development->cluster_data->project_size,
                "developer_sales_banner" => $decoded_data->development->cluster_data->developer_sales_banner,
                "gallery" => $decoded_data->development->cluster_data->photos,
                "listings_summary" => array(
                    "rent" => $rent,
                    "sale" => $sale
                ),
                "listings_summary_text" => $decoded_data->development->cluster_data->listings_summary_text,
                "nearby_place" => $decoded_data->development->commute->data->places,
                "floorplan" => $floor_plans_data
            );

            $this->updateData(json_encode($result));
            return;
        }catch (Throwable $e){
            print($e . "\r\n");

        }finally{

        }
        return;

    }

    private function getElementValue($element,$attribute, $value){
        $dom = new DOMDocument();
        @$dom->loadHTML($element);

        $finder = new DOMXPath($dom);
        $result = $finder->query("//*[contains(concat(' ', normalize-space(@".$attribute."), ' '), ' $value ')]");

        return $result->length ? $result[0]->textContent : '';
    }

    private function getElementValueWithExt($element,$attribute, $value,$ext){
        $dom = new DOMDocument();
        @$dom->loadHTML($element);

        $finder = new DOMXPath($dom);
        $result = $finder->query("//*[contains(concat(' ', normalize-space(@".$attribute."), ' '), ' $value ')]".$ext);

        return $result->length ? $result[0]->textContent : '';
    }

    function getFloorPlanObject($arr,$key){
        $value = '';
        foreach ($arr as $item){
            if($item->floor_plan_key == $key){
                $value = $item;
            }
        }
        return $value;
    }

    public function savePhoto($url,$saveto){
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
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL , 1);
            curl_setopt($ch, CURLOPT_PROXY, $proxyIP );
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyUsername:$proxyPassword");
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
            $raw=curl_exec($ch);
            curl_close($ch);

        }
        if(file_exists($saveto)){
            unlink($saveto);
        }
        $fp = fopen($saveto,'x');
        fwrite($fp, $raw);
        fclose($fp);
    }

    public function updateData($data){
        try{
            $decode_data = json_decode($data);
            $exists = $this->connection->createCommand("SELECT * FROM xp_pn_99co_projects WHERE name = '$decode_data->name'")->queryAll();

            if(!$exists){
                try{
                    $this->connection->createCommand()
                        ->insert(
                            'xp_pn_99co_projects',
                            array(
                                'json' => $data,
                                'name' => $decode_data->name,
                                'address' => $decode_data->address,
                                'property_type' => $decode_data->property_type
                            )
                        );

                }catch(Throwable $e){
                    print($e. "\r\n");
                }finally{

                }

                try{
                    $last_record = $this->connection->createCommand("SELECT id FROM xp_pn_99co_projects ORDER BY id DESC LIMIT 1")->queryAll();
                    $id = json_decode(json_encode($last_record))[0]->id;
                }
                catch(Throwable $e){
                    print($e. "\r\n");
                }finally{

                }


                foreach ($decode_data->floorplan as $item){
                    try{
                        $this->connection->createCommand()
                            ->insert(
                                'xp_pn_99co_floorplans',
                                array(
                                    'project_id' => $id,
                                    'title' => $item->title,
                                    'area' => $item->area,
                                    'bedroom' => $item->bedrooms,
                                    'bathroom' => $item->bathroom,
                                    'source_photo_url' => $item->photo,
                                    'processed_photo_url' => $item->processed_photo
                                )
                            );
                    }catch(Throwable $e){
                        print($e. "\r\n");
                    }finally{

                    }
                }
                print("inserted \r\n");


            }else{
                try{
                    $this->connection->createCommand()
                        ->update(
                            'xp_pn_99co_projects',
                            array(
                                'json' => $data,
                                'name' => $decode_data->name,
                                'address' => $decode_data->address,
                                'property_type' => $decode_data->property_type
                            ),'name=:name',array(':name' =>$decode_data->name)
                        );


                    $project_id = json_decode(json_encode($exists))[0]->id;

                    try{
                        $this->connection->createCommand("DELETE FROM xp_pn_99co_floorplans WHERE project_id =' $project_id'")->execute();
                    }catch(Throwable $e){
                        print($e. "\r\n");
                    }finally{

                    }

                    foreach ($decode_data->floorplan as $item){

                        try{
                            $this->connection->createCommand()
                                ->insert(
                                    'xp_pn_99co_floorplans',
                                    array(
                                        'project_id' => $project_id,
                                        'title' => $item->title,
                                        'area' => $item->area,
                                        'bedroom' => $item->bedrooms,
                                        'bathroom' => $item->bathroom,
                                        'source_photo_url' => $item->photo,
                                        'processed_photo_url' => $item->processed_photo
                                    )
                                );
                        }catch (Throwable $e){
                            print($e. "\r\n");
                        }finally{

                        }
                    }

                    print("updated \r\n");
                }catch(Throwable $e){
                    print($e. "\r\n");
                }finally{

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
//        if( empty($data) || !is_callable('curl_init') ) {
//            $opts = array('http'=>array('header' => 'Connection: close'));
//            $context = stream_context_create($opts);
//            $headers = get_headers($url);
//            $httpcode = substr($headers[0], 9, 3);
//            if( $httpcode == '200' )
//                $data = file_get_contents($url, false, $context);
//            else{
//                $data = '{"div":"Error ' . $httpcode . ': Invalid Url<br />"}';
//            }
//        }
        return $data;
    }



}
