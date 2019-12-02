<?php

class ListingsCommand extends CConsoleCommand
{

    private $connection;

    /**
     * Basic class setup
     */
    public function init() {
        $this->connection = Yii::app()->db;
    }

    public function getProxy(){
        $proxies = array(
            "195.154.161.107:9791",
            "195.154.161.107:9792",
            "195.154.161.107:9793",
            "195.154.161.107:9794",
            "195.154.161.107:9795",
            "195.154.161.107:9796",
            "195.154.161.107:9797",
            "195.154.161.107:9798",
            "195.154.161.107:9799",
            "195.154.161.107:9800",
        );
        return $proxies[array_rand($proxies)];
    }
    public function actionListingOneScrapeData(){

        $this->scrapeOneListing();
        $response_json = array(
            'status' => 'OK',
            'message' => 'Scraping Successfully'
        );
        echo json_encode($response_json);
        return;
    }

    public function actionListingTwoScrapeData(){

        $this->scrapeTwoListing();
        $response_json = array(
            'status' => 'OK',
            'message' => 'Scraping Successfully'
        );
        echo json_encode($response_json);
        return;
    }

    public function actionListingThreeScrapeData(){

        $this->scrapeThreeListing();
        $response_json = array(
            'status' => 'OK',
            'message' => 'Scraping Successfully'
        );
        echo json_encode($response_json);
        return;
    }

    public function actionListingFourScrapeData(){

        $this->scrapeFourListing();
        $response_json = array(
            'status' => 'OK',
            'message' => 'Scraping Successfully'
        );
        echo json_encode($response_json);
        return;
    }

    public function actionListingFiveScrapeData(){

        $this->scrapeFiveListing();
        $response_json = array(
            'status' => 'OK',
            'message' => 'Scraping Successfully'
        );
        echo json_encode($response_json);
        return;
    }

    public function actionListingSixScrapeData(){

        $this->scrapeSixListing();
        $response_json = array(
            'status' => 'OK',
            'message' => 'Scraping Successfully'
        );
        echo json_encode($response_json);
        return;
    }

    public function scrapeOneListing(){
        $url = 'https://www.propertyguru.com.sg/property-for-sale?sort=price&order=asc&market=residential&property_type_code%5B%5D=CONDO&property_type_code%5B%5D=APT&property_type_code%5B%5D=WALK&property_type_code%5B%5D=CLUS&property_type_code%5B%5D=EXCON&property_type=N&newProject=all';
        while ( 1 === 1){
            $cards = array();
            $dom = new DOMDocument();

            $tries = 0;
            while(1 === 1){
                sleep(10);
                $html = $this->qr_loadUrl($url);
                @$dom->loadHTML($html);
                $finder = new DOMXPath($dom);

                $classname = "listing-card";
                $cards = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                if ($cards->length > 0){
                    print("success : ". $url. "\r\n");
                    break;
                }

                if($tries == 10){
                    sleep(600);
                }

                print("failed attempt! \r\n");
                $tries = $tries +1;

            }


            foreach($cards as $card){
                $card_element = $dom->saveHTML($card);
                $link = 'https://propertyguru.com.sg'.$this->getElementValueWithExt($card_element,'itemprop','name','//a/@href');
                $name = $this->getElementValue($card_element, 'itemprop','name');
                $address = $this->getElementValue($card_element,'itemprop','streetAddress');
                $listed_by = $this->getElementValueWithExt($card_element,'class','agent-name','/span[2]');

                if($this->skipData($name, $address,$listed_by) == true){
                    print ("skipped data \r\n");
                    continue;
                }

                $tries = 0;
                $dom_for_details = new DOMDocument();
                while(1 === 1){
                    sleep(10);
                    $html = $this->qr_loadUrl($link);
                    @$dom_for_details->loadHTML($html);
                    $finder_details = new DOMXPath($dom_for_details);

                    $name = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-title ')]");
                    if ($name->length > 0){
                        $property_name = $name[0]->nodeValue;
                        $currency = $this->getNodeValue($finder_details,'itemprop','priceCurrency');
                        $amount = $this->getNodeValueWithExt($finder_details,'itemprop','price','/@content');
                        $price = $currency .' '. $amount;
                        $streetAddress = $this->getNodeValue($finder_details,'itemprop','streetAddress');
                        $postalCode = $this->getNodeValue($finder_details,'itemprop','postalCode');
                        $addressLocality = $this->getNodeValue($finder_details,'itemprop','addressLocality');
                        $estate = $this->getNodeValue($finder_details,'itemprop','addressRegion');

                        $look_for_district = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-address ')]")[0]->childNodes;
                        $district = "";
                        foreach ($look_for_district as $item){
                            if($item->nodeName == "#text"){
                                $district = trim($item->nodeValue);
                            }
                        }
                        $latitude = $this->getNodeValueWithExt($finder_details,'itemprop','latitude','/@content');
                        $longitude = $this->getNodeValueWithExt($finder_details,'itemprop','longitude','/@content');
                        $details = $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' additionalProperty ')]");

                        $details_array = new StdClass;
                        foreach ($details as $detail){
                            $detail_element = $dom_for_details->saveHTML($detail);
                            $attribute = $this->getElementValue($detail_element,'itemprop','name');
                            $value = $this->getElementValue($detail_element,'itemprop','value');
                            $details_array->{str_replace(' ','_',$attribute)} = $value;
                        }
                        $description =  $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' description ')]")[0];
                        $buffer = array();
                        foreach ($description->childNodes as $node ){
                            if($node->nodeName === '#text' && trim(preg_replace("/\r\n/", '', $node->nodeValue)) !== '') {
                                $buffer[] = $node->nodeValue;
                            }
                        }

                        $agent = $this->getNodeValueWithExt($finder_details,'class','list-group-item-heading','//a');
                        $agencyName = $listed_by;
                        $agencyLicense = $this->getNodeValue($finder_details,'class','agent-license');
                        $contact = $this->getNodeValue($finder_details,'class','gallery-form__thankyou__agent-phone');

                        $projectInfo = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-details-primary ')]");
                        $projectInfoObject = new StdClass;
                        if(isset($projectInfo[1])){
                            $projectInfoElement = $dom_for_details->saveHTML($projectInfo[1]);
                            $projectInfos = $this->getElementChildNodes($projectInfoElement,'class','property-attr');

                            foreach ($projectInfos  as $info){
                                $attribute = $info->childNodes[1]->textContent;
                                $value = $info->childNodes[3]->textContent;
                                $projectInfoObject->{str_replace(' ','_',$attribute)} = $value;
                            }
                        }else{
                            $projectInfoObject = null;
                        }

                        $description_final = str_replace("\r",' ',trim(str_replace("\n",'',implode("\r\n", $buffer))));
                        $result_json = json_encode( array(
                                "property_name" => $property_name,
                                "price" => $price,
                                "streetAddress" => $streetAddress,
                                "postalCode" => $postalCode,
                                "addressLocality" => $addressLocality,
                                "district" => $district,
                                "estate" => $estate,
                                "latitude" => $latitude,
                                "longitude" => $longitude,
                                "details" => $details_array,
                                "projectInfo" => $projectInfoObject,
                                "description" => $description_final,
                                "agentName" => $agent,
                                "agencyName" => $agencyName,
                                "agencyLicense" => $agencyLicense,
                                "contact" => trim($contact)
                            )
                        );

                        $this->updateData($result_json);
                        break;
                    }

                    if($tries == 10){
                        sleep(600);
                    }

                    print("failed attempt! :" . $link ."\r\n");
                    $tries = $tries +1;
                }
            }

            $next_pagination_url = $this->getNodeValueWithExt($finder,'class','pagination-next','//a/@href');

            if ($next_pagination_url != ''){
                $url = 'https://www.propertyguru.com.sg'.$next_pagination_url;
            }else{
                break;
            }
        }

        return;
    }

    public function scrapeTwoListing(){
        $url = 'https://www.propertyguru.com.sg/property-for-sale?sort=price&order=asc&market=residential&property_type_code%5B%5D=TERRA&property_type_code%5B%5D=DETAC&property_type_code%5B%5D=SEMI&property_type_code%5B%5D=CORN&property_type_code%5B%5D=LBUNG&property_type_code%5B%5D=BUNG&property_type_code%5B%5D=SHOPH&property_type_code%5B%5D=RLAND&property_type_code%5B%5D=TOWN&property_type_code%5B%5D=CON&property_type_code%5B%5D=LCLUS&property_type=L&newProject=all';
        while ( 1 === 1){
            $cards = array();
            $dom = new DOMDocument();

            $tries = 0;
            while(1 === 1){
                sleep(10);
                $html = $this->qr_loadUrl($url);
                @$dom->loadHTML($html);
                $finder = new DOMXPath($dom);

                $classname = "listing-card";
                $cards = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                if ($cards->length > 0){
                    print("success : ". $url. "\r\n");
                    break;
                }

                if($tries == 10){
                    sleep(600);
                }

                print("failed attempt! \r\n");
                $tries = $tries +1;

            }


            foreach($cards as $card){
                $card_element = $dom->saveHTML($card);
                $link = 'https://propertyguru.com.sg'.$this->getElementValueWithExt($card_element,'itemprop','name','//a/@href');
                $name = $this->getElementValue($card_element, 'itemprop','name');
                $address = $this->getElementValue($card_element,'itemprop','streetAddress');
                $listed_by = $this->getElementValueWithExt($card_element,'class','agent-name','/span[2]');

                if($this->skipData($name, $address,$listed_by) == true){
                    print ("skipped data \r\n");
                    continue;
                }

                $tries = 0;
                $dom_for_details = new DOMDocument();
                while(1 === 1){
                    sleep(10);
                    $html = $this->qr_loadUrl($link);
                    @$dom_for_details->loadHTML($html);
                    $finder_details = new DOMXPath($dom_for_details);

                    $name = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-title ')]");
                    if ($name->length > 0){
                        $property_name = $name[0]->nodeValue;
                        $currency = $this->getNodeValue($finder_details,'itemprop','priceCurrency');
                        $amount = $this->getNodeValueWithExt($finder_details,'itemprop','price','/@content');
                        $price = $currency .' '. $amount;
                        $streetAddress = $this->getNodeValue($finder_details,'itemprop','streetAddress');
                        $postalCode = $this->getNodeValue($finder_details,'itemprop','postalCode');
                        $addressLocality = $this->getNodeValue($finder_details,'itemprop','addressLocality');
                        $estate = $this->getNodeValue($finder_details,'itemprop','addressRegion');

                        $look_for_district = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-address ')]")[0]->childNodes;
                        $district = "";
                        foreach ($look_for_district as $item){
                            if($item->nodeName == "#text"){
                                $district = trim($item->nodeValue);
                            }
                        }
                        $latitude = $this->getNodeValueWithExt($finder_details,'itemprop','latitude','/@content');
                        $longitude = $this->getNodeValueWithExt($finder_details,'itemprop','longitude','/@content');
                        $details = $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' additionalProperty ')]");

                        $details_array = new StdClass;
                        foreach ($details as $detail){
                            $detail_element = $dom_for_details->saveHTML($detail);
                            $attribute = $this->getElementValue($detail_element,'itemprop','name');
                            $value = $this->getElementValue($detail_element,'itemprop','value');
                            $details_array->{str_replace(' ','_',$attribute)} = $value;
                        }
                        $description =  $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' description ')]")[0];
                        $buffer = array();
                        foreach ($description->childNodes as $node ){
                            if($node->nodeName === '#text' && trim(preg_replace("/\r\n/", '', $node->nodeValue)) !== '') {
                                $buffer[] = $node->nodeValue;
                            }
                        }

                        $agent = $this->getNodeValueWithExt($finder_details,'class','list-group-item-heading','//a');
                        $agencyName = $listed_by;
                        $agencyLicense = $this->getNodeValue($finder_details,'class','agent-license');
                        $contact = $this->getNodeValue($finder_details,'class','gallery-form__thankyou__agent-phone');

                        $projectInfo = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-details-primary ')]");
                        $projectInfoObject = new StdClass;
                        if(isset($projectInfo[1])){
                            $projectInfoElement = $dom_for_details->saveHTML($projectInfo[1]);
                            $projectInfos = $this->getElementChildNodes($projectInfoElement,'class','property-attr');

                            foreach ($projectInfos  as $info){
                                $attribute = $info->childNodes[1]->textContent;
                                $value = $info->childNodes[3]->textContent;
                                $projectInfoObject->{str_replace(' ','_',$attribute)} = $value;
                            }
                        }else{
                            $projectInfoObject = null;
                        }

                        $description_final = str_replace("\r",' ',trim(str_replace("\n",'',implode("\r\n", $buffer))));
                        $result_json = json_encode( array(
                                "property_name" => $property_name,
                                "price" => $price,
                                "streetAddress" => $streetAddress,
                                "postalCode" => $postalCode,
                                "addressLocality" => $addressLocality,
                                "district" => $district,
                                "estate" => $estate,
                                "latitude" => $latitude,
                                "longitude" => $longitude,
                                "details" => $details_array,
                                "projectInfo" => $projectInfoObject,
                                "description" => $description_final,
                                "agentName" => $agent,
                                "agencyName" => $agencyName,
                                "agencyLicense" => $agencyLicense,
                                "contact" => trim($contact)
                            )
                        );

                        $this->updateData($result_json);
                        break;
                    }

                    if($tries == 10){
                        sleep(600);
                    }

                    print("failed attempt! :" . $link ."\r\n");
                    $tries = $tries +1;
                }
            }

            $next_pagination_url = $this->getNodeValueWithExt($finder,'class','pagination-next','//a/@href');

            if ($next_pagination_url != ''){
                $url = 'https://www.propertyguru.com.sg'.$next_pagination_url;
            }else{
                break;
            }
        }

        return;
    }

    public function scrapeThreeListing(){
        $url = 'https://www.propertyguru.com.sg/property-for-sale?sort=price&order=asc&market=residential&property_type_code%5B%5D=1R&property_type_code%5B%5D=2A&property_type_code%5B%5D=2I&property_type_code%5B%5D=2S&property_type_code%5B%5D=3A&property_type_code%5B%5D=3NG&property_type_code%5B%5D=3Am&property_type_code%5B%5D=3NGm&property_type_code%5B%5D=3I&property_type_code%5B%5D=3Im&property_type_code%5B%5D=3S&property_type_code%5B%5D=3STD&property_type_code%5B%5D=4A&property_type_code%5B%5D=4NG&property_type_code%5B%5D=4S&property_type_code%5B%5D=4I&property_type_code%5B%5D=4STD&property_type_code%5B%5D=5A&property_type_code%5B%5D=5I&property_type_code%5B%5D=5S&property_type_code%5B%5D=6J&property_type_code%5B%5D=EA&property_type_code%5B%5D=EM&property_type_code%5B%5D=MG&property_type_code%5B%5D=TE&property_type=H&newProject=all';
        while ( 1 === 1){
            $cards = array();
            $dom = new DOMDocument();

            $tries = 0;
            while(1 === 1){
                sleep(10);
                $html = $this->qr_loadUrl($url);
                @$dom->loadHTML($html);
                $finder = new DOMXPath($dom);

                $classname = "listing-card";
                $cards = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                if ($cards->length > 0){
                    print("success : ". $url. "\r\n");
                    break;
                }

                if($tries == 10){
                    sleep(600);
                }

                print("failed attempt! \r\n");
                $tries = $tries +1;

            }


            foreach($cards as $card){
                $card_element = $dom->saveHTML($card);
                $link = 'https://propertyguru.com.sg'.$this->getElementValueWithExt($card_element,'itemprop','name','//a/@href');
                $name = $this->getElementValue($card_element, 'itemprop','name');
                $address = $this->getElementValue($card_element,'itemprop','streetAddress');
                $listed_by = $this->getElementValueWithExt($card_element,'class','agent-name','/span[2]');

                if($this->skipData($name, $address,$listed_by) == true){
                    print ("skipped data \r\n");
                    continue;
                }

                $tries = 0;
                $dom_for_details = new DOMDocument();
                while(1 === 1){
                    sleep(10);
                    $html = $this->qr_loadUrl($link);
                    @$dom_for_details->loadHTML($html);
                    $finder_details = new DOMXPath($dom_for_details);

                    $name = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-title ')]");
                    if ($name->length > 0){
                        $property_name = $name[0]->nodeValue;
                        $currency = $this->getNodeValue($finder_details,'itemprop','priceCurrency');
                        $amount = $this->getNodeValueWithExt($finder_details,'itemprop','price','/@content');
                        $price = $currency .' '. $amount;
                        $streetAddress = $this->getNodeValue($finder_details,'itemprop','streetAddress');
                        $postalCode = $this->getNodeValue($finder_details,'itemprop','postalCode');
                        $addressLocality = $this->getNodeValue($finder_details,'itemprop','addressLocality');
                        $estate = $this->getNodeValue($finder_details,'itemprop','addressRegion');

                        $look_for_district = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-address ')]")[0]->childNodes;
                        $district = "";
                        foreach ($look_for_district as $item){
                            if($item->nodeName == "#text"){
                                $district = trim($item->nodeValue);
                            }
                        }
                        $latitude = $this->getNodeValueWithExt($finder_details,'itemprop','latitude','/@content');
                        $longitude = $this->getNodeValueWithExt($finder_details,'itemprop','longitude','/@content');
                        $details = $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' additionalProperty ')]");

                        $details_array = new StdClass;
                        foreach ($details as $detail){
                            $detail_element = $dom_for_details->saveHTML($detail);
                            $attribute = $this->getElementValue($detail_element,'itemprop','name');
                            $value = $this->getElementValue($detail_element,'itemprop','value');
                            $details_array->{str_replace(' ','_',$attribute)} = $value;
                        }
                        $description =  $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' description ')]")[0];
                        $buffer = array();
                        foreach ($description->childNodes as $node ){
                            if($node->nodeName === '#text' && trim(preg_replace("/\r\n/", '', $node->nodeValue)) !== '') {
                                $buffer[] = $node->nodeValue;
                            }
                        }

                        $agent = $this->getNodeValueWithExt($finder_details,'class','list-group-item-heading','//a');
                        $agencyName = $listed_by;
                        $agencyLicense = $this->getNodeValue($finder_details,'class','agent-license');
                        $contact = $this->getNodeValue($finder_details,'class','gallery-form__thankyou__agent-phone');

                        $projectInfo = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-details-primary ')]");
                        $projectInfoObject = new StdClass;
                        if(isset($projectInfo[1])){
                            $projectInfoElement = $dom_for_details->saveHTML($projectInfo[1]);
                            $projectInfos = $this->getElementChildNodes($projectInfoElement,'class','property-attr');

                            foreach ($projectInfos  as $info){
                                $attribute = $info->childNodes[1]->textContent;
                                $value = $info->childNodes[3]->textContent;
                                $projectInfoObject->{str_replace(' ','_',$attribute)} = $value;
                            }
                        }else{
                            $projectInfoObject = null;
                        }

                        $description_final = str_replace("\r",' ',trim(str_replace("\n",'',implode("\r\n", $buffer))));
                        $result_json = json_encode( array(
                                "property_name" => $property_name,
                                "price" => $price,
                                "streetAddress" => $streetAddress,
                                "postalCode" => $postalCode,
                                "addressLocality" => $addressLocality,
                                "district" => $district,
                                "estate" => $estate,
                                "latitude" => $latitude,
                                "longitude" => $longitude,
                                "details" => $details_array,
                                "projectInfo" => $projectInfoObject,
                                "description" => $description_final,
                                "agentName" => $agent,
                                "agencyName" => $agencyName,
                                "agencyLicense" => $agencyLicense,
                                "contact" => trim($contact)
                            )
                        );

                        $this->updateData($result_json);
                        break;
                    }

                    if($tries == 10){
                        sleep(600);
                    }

                    print("failed attempt! :" . $link ."\r\n");
                    $tries = $tries +1;
                }
            }

            $next_pagination_url = $this->getNodeValueWithExt($finder,'class','pagination-next','//a/@href');

            if ($next_pagination_url != ''){
                $url = 'https://www.propertyguru.com.sg'.$next_pagination_url;
            }else{
                break;
            }
        }

        return;
    }

    public function scrapeFourListing(){
        $url = 'https://www.propertyguru.com.sg/property-for-rent?sort=price&order=asc&market=residential&property_type_code%5B%5D=CONDO&property_type_code%5B%5D=APT&property_type_code%5B%5D=WALK&property_type_code%5B%5D=CLUS&property_type_code%5B%5D=EXCON&property_type=N&newProject=all';
        while ( 1 === 1){
            $cards = array();
            $dom = new DOMDocument();

            $tries = 0;
            while(1 === 1){
                sleep(10);
                $html = $this->qr_loadUrl($url);
                @$dom->loadHTML($html);
                $finder = new DOMXPath($dom);

                $classname = "listing-card";
                $cards = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                if ($cards->length > 0){
                    print("success : ". $url. "\r\n");
                    break;
                }

                if($tries == 10){
                    sleep(600);
                }

                print("failed attempt! \r\n");
                $tries = $tries +1;

            }


            foreach($cards as $card){
                $card_element = $dom->saveHTML($card);
                $link = 'https://propertyguru.com.sg'.$this->getElementValueWithExt($card_element,'itemprop','name','//a/@href');
                $name = $this->getElementValue($card_element, 'itemprop','name');
                $address = $this->getElementValue($card_element,'itemprop','streetAddress');
                $listed_by = $this->getElementValueWithExt($card_element,'class','agent-name','/span[2]');

                if($this->skipData($name, $address,$listed_by) == true){
                    print ("skipped data \r\n");
                    continue;
                }

                $tries = 0;
                $dom_for_details = new DOMDocument();
                while(1 === 1){
                    sleep(10);
                    $html = $this->qr_loadUrl($link);
                    @$dom_for_details->loadHTML($html);
                    $finder_details = new DOMXPath($dom_for_details);

                    $name = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-title ')]");
                    if ($name->length > 0){
                        $property_name = $name[0]->nodeValue;
                        $currency = $this->getNodeValue($finder_details,'itemprop','priceCurrency');
                        $amount = $this->getNodeValueWithExt($finder_details,'itemprop','price','/@content');
                        $price = $currency .' '. $amount;
                        $streetAddress = $this->getNodeValue($finder_details,'itemprop','streetAddress');
                        $postalCode = $this->getNodeValue($finder_details,'itemprop','postalCode');
                        $addressLocality = $this->getNodeValue($finder_details,'itemprop','addressLocality');
                        $estate = $this->getNodeValue($finder_details,'itemprop','addressRegion');

                        $look_for_district = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-address ')]")[0]->childNodes;
                        $district = "";
                        foreach ($look_for_district as $item){
                            if($item->nodeName == "#text"){
                                $district = trim($item->nodeValue);
                            }
                        }
                        $latitude = $this->getNodeValueWithExt($finder_details,'itemprop','latitude','/@content');
                        $longitude = $this->getNodeValueWithExt($finder_details,'itemprop','longitude','/@content');
                        $details = $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' additionalProperty ')]");

                        $details_array = new StdClass;
                        foreach ($details as $detail){
                            $detail_element = $dom_for_details->saveHTML($detail);
                            $attribute = $this->getElementValue($detail_element,'itemprop','name');
                            $value = $this->getElementValue($detail_element,'itemprop','value');
                            $details_array->{str_replace(' ','_',$attribute)} = $value;
                        }
                        $description =  $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' description ')]")[0];
                        $buffer = array();
                        foreach ($description->childNodes as $node ){
                            if($node->nodeName === '#text' && trim(preg_replace("/\r\n/", '', $node->nodeValue)) !== '') {
                                $buffer[] = $node->nodeValue;
                            }
                        }

                        $agent = $this->getNodeValueWithExt($finder_details,'class','list-group-item-heading','//a');
                        $agencyName = $listed_by;
                        $agencyLicense = $this->getNodeValue($finder_details,'class','agent-license');
                        $contact = $this->getNodeValue($finder_details,'class','gallery-form__thankyou__agent-phone');

                        $projectInfo = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-details-primary ')]");
                        $projectInfoObject = new StdClass;
                        if(isset($projectInfo[1])){
                            $projectInfoElement = $dom_for_details->saveHTML($projectInfo[1]);
                            $projectInfos = $this->getElementChildNodes($projectInfoElement,'class','property-attr');

                            foreach ($projectInfos  as $info){
                                $attribute = $info->childNodes[1]->textContent;
                                $value = $info->childNodes[3]->textContent;
                                $projectInfoObject->{str_replace(' ','_',$attribute)} = $value;
                            }
                        }else{
                            $projectInfoObject = null;
                        }

                        $description_final = str_replace("\r",' ',trim(str_replace("\n",'',implode("\r\n", $buffer))));
                        $result_json = json_encode( array(
                                "property_name" => $property_name,
                                "price" => $price,
                                "streetAddress" => $streetAddress,
                                "postalCode" => $postalCode,
                                "addressLocality" => $addressLocality,
                                "district" => $district,
                                "estate" => $estate,
                                "latitude" => $latitude,
                                "longitude" => $longitude,
                                "details" => $details_array,
                                "projectInfo" => $projectInfoObject,
                                "description" => $description_final,
                                "agentName" => $agent,
                                "agencyName" => $agencyName,
                                "agencyLicense" => $agencyLicense,
                                "contact" => trim($contact)
                            )
                        );

                        $this->updateData($result_json);
                        break;
                    }

                    if($tries == 10){
                        sleep(600);
                    }

                    print("failed attempt! :" . $link ."\r\n");
                    $tries = $tries +1;
                }
            }

            $next_pagination_url = $this->getNodeValueWithExt($finder,'class','pagination-next','//a/@href');

            if ($next_pagination_url != ''){
                $url = 'https://www.propertyguru.com.sg'.$next_pagination_url;
            }else{
                break;
            }
        }

        return;
    }

    public function scrapeFiveListing(){
        $url = 'https://www.propertyguru.com.sg/property-for-rent?sort=price&order=asc&market=residential&property_type_code%5B%5D=TERRA&property_type_code%5B%5D=DETAC&property_type_code%5B%5D=SEMI&property_type_code%5B%5D=CORN&property_type_code%5B%5D=LBUNG&property_type_code%5B%5D=BUNG&property_type_code%5B%5D=SHOPH&property_type_code%5B%5D=RLAND&property_type_code%5B%5D=TOWN&property_type_code%5B%5D=CON&property_type_code%5B%5D=LCLUS&property_type=L&newProject=all';
        while ( 1 === 1){
            $cards = array();
            $dom = new DOMDocument();

            $tries = 0;
            while(1 === 1){
                sleep(10);
                $html = $this->qr_loadUrl($url);
                @$dom->loadHTML($html);
                $finder = new DOMXPath($dom);

                $classname = "listing-card";
                $cards = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                if ($cards->length > 0){
                    print("success : ". $url. "\r\n");
                    break;
                }

                if($tries == 10){
                    sleep(600);
                }

                print("failed attempt! \r\n");
                $tries = $tries +1;

            }


            foreach($cards as $card){
                $card_element = $dom->saveHTML($card);
                $link = 'https://propertyguru.com.sg'.$this->getElementValueWithExt($card_element,'itemprop','name','//a/@href');
                $name = $this->getElementValue($card_element, 'itemprop','name');
                $address = $this->getElementValue($card_element,'itemprop','streetAddress');
                $listed_by = $this->getElementValueWithExt($card_element,'class','agent-name','/span[2]');

                if($this->skipData($name, $address,$listed_by) == true){
                    print ("skipped data \r\n");
                    continue;
                }

                $tries = 0;
                $dom_for_details = new DOMDocument();
                while(1 === 1){
                    sleep(10);
                    $html = $this->qr_loadUrl($link);
                    @$dom_for_details->loadHTML($html);
                    $finder_details = new DOMXPath($dom_for_details);

                    $name = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-title ')]");
                    if ($name->length > 0){
                        $property_name = $name[0]->nodeValue;
                        $currency = $this->getNodeValue($finder_details,'itemprop','priceCurrency');
                        $amount = $this->getNodeValueWithExt($finder_details,'itemprop','price','/@content');
                        $price = $currency .' '. $amount;
                        $streetAddress = $this->getNodeValue($finder_details,'itemprop','streetAddress');
                        $postalCode = $this->getNodeValue($finder_details,'itemprop','postalCode');
                        $addressLocality = $this->getNodeValue($finder_details,'itemprop','addressLocality');
                        $estate = $this->getNodeValue($finder_details,'itemprop','addressRegion');

                        $look_for_district = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-address ')]")[0]->childNodes;
                        $district = "";
                        foreach ($look_for_district as $item){
                            if($item->nodeName == "#text"){
                                $district = trim($item->nodeValue);
                            }
                        }
                        $latitude = $this->getNodeValueWithExt($finder_details,'itemprop','latitude','/@content');
                        $longitude = $this->getNodeValueWithExt($finder_details,'itemprop','longitude','/@content');
                        $details = $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' additionalProperty ')]");

                        $details_array = new StdClass;
                        foreach ($details as $detail){
                            $detail_element = $dom_for_details->saveHTML($detail);
                            $attribute = $this->getElementValue($detail_element,'itemprop','name');
                            $value = $this->getElementValue($detail_element,'itemprop','value');
                            $details_array->{str_replace(' ','_',$attribute)} = $value;
                        }
                        $description =  $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' description ')]")[0];
                        $buffer = array();
                        foreach ($description->childNodes as $node ){
                            if($node->nodeName === '#text' && trim(preg_replace("/\r\n/", '', $node->nodeValue)) !== '') {
                                $buffer[] = $node->nodeValue;
                            }
                        }

                        $agent = $this->getNodeValueWithExt($finder_details,'class','list-group-item-heading','//a');
                        $agencyName = $listed_by;
                        $agencyLicense = $this->getNodeValue($finder_details,'class','agent-license');
                        $contact = $this->getNodeValue($finder_details,'class','gallery-form__thankyou__agent-phone');

                        $projectInfo = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-details-primary ')]");
                        $projectInfoObject = new StdClass;
                        if(isset($projectInfo[1])){
                            $projectInfoElement = $dom_for_details->saveHTML($projectInfo[1]);
                            $projectInfos = $this->getElementChildNodes($projectInfoElement,'class','property-attr');

                            foreach ($projectInfos  as $info){
                                $attribute = $info->childNodes[1]->textContent;
                                $value = $info->childNodes[3]->textContent;
                                $projectInfoObject->{str_replace(' ','_',$attribute)} = $value;
                            }
                        }else{
                            $projectInfoObject = null;
                        }

                        $description_final = str_replace("\r",' ',trim(str_replace("\n",'',implode("\r\n", $buffer))));
                        $result_json = json_encode( array(
                                "property_name" => $property_name,
                                "price" => $price,
                                "streetAddress" => $streetAddress,
                                "postalCode" => $postalCode,
                                "addressLocality" => $addressLocality,
                                "district" => $district,
                                "estate" => $estate,
                                "latitude" => $latitude,
                                "longitude" => $longitude,
                                "details" => $details_array,
                                "projectInfo" => $projectInfoObject,
                                "description" => $description_final,
                                "agentName" => $agent,
                                "agencyName" => $agencyName,
                                "agencyLicense" => $agencyLicense,
                                "contact" => trim($contact)
                            )
                        );

                        $this->updateData($result_json);
                        break;
                    }

                    if($tries == 10){
                        sleep(600);
                    }

                    print("failed attempt! :" . $link ."\r\n");
                    $tries = $tries +1;
                }
            }

            $next_pagination_url = $this->getNodeValueWithExt($finder,'class','pagination-next','//a/@href');

            if ($next_pagination_url != ''){
                $url = 'https://www.propertyguru.com.sg'.$next_pagination_url;
            }else{
                break;
            }
        }

        return;
    }

    public function scrapeSixListing(){
        $url = 'https://www.propertyguru.com.sg/property-for-rent?sort=price&order=asc&market=residential&property_type_code%5B%5D=1R&property_type_code%5B%5D=2A&property_type_code%5B%5D=2I&property_type_code%5B%5D=2S&property_type_code%5B%5D=3A&property_type_code%5B%5D=3NG&property_type_code%5B%5D=3Am&property_type_code%5B%5D=3NGm&property_type_code%5B%5D=3I&property_type_code%5B%5D=3Im&property_type_code%5B%5D=3S&property_type_code%5B%5D=3STD&property_type_code%5B%5D=4A&property_type_code%5B%5D=4NG&property_type_code%5B%5D=4S&property_type_code%5B%5D=4I&property_type_code%5B%5D=4STD&property_type_code%5B%5D=5A&property_type_code%5B%5D=5I&property_type_code%5B%5D=5S&property_type_code%5B%5D=6J&property_type_code%5B%5D=EA&property_type_code%5B%5D=EM&property_type_code%5B%5D=MG&property_type_code%5B%5D=TE&property_type=H&newProject=all';
        while ( 1 === 1){
            $cards = array();
            $dom = new DOMDocument();

            $tries = 0;
            while(1 === 1){
                sleep(10);
                $html = $this->qr_loadUrl($url);
                @$dom->loadHTML($html);
                $finder = new DOMXPath($dom);

                $classname = "listing-card";
                $cards = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

                if ($cards->length > 0){
                    print("success : ". $url. "\r\n");
                    break;
                }

                if($tries == 10){
                    sleep(600);
                }

                print("failed attempt! \r\n");
                $tries = $tries +1;

            }


            foreach($cards as $card){
                $card_element = $dom->saveHTML($card);
                $link = 'https://propertyguru.com.sg'.$this->getElementValueWithExt($card_element,'itemprop','name','//a/@href');
                $name = $this->getElementValue($card_element, 'itemprop','name');
                $address = $this->getElementValue($card_element,'itemprop','streetAddress');
                $listed_by = $this->getElementValueWithExt($card_element,'class','agent-name','/span[2]');

                if($this->skipData($name, $address,$listed_by) == true){
                    print ("skipped data \r\n");
                    continue;
                }

                $tries = 0;
                $dom_for_details = new DOMDocument();
                while(1 === 1){
                    sleep(10);
                    $html = $this->qr_loadUrl($link);
                    @$dom_for_details->loadHTML($html);
                    $finder_details = new DOMXPath($dom_for_details);

                    $name = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-title ')]");
                    if ($name->length > 0){
                        $property_name = $name[0]->nodeValue;
                        $currency = $this->getNodeValue($finder_details,'itemprop','priceCurrency');
                        $amount = $this->getNodeValueWithExt($finder_details,'itemprop','price','/@content');
                        $price = $currency .' '. $amount;
                        $streetAddress = $this->getNodeValue($finder_details,'itemprop','streetAddress');
                        $postalCode = $this->getNodeValue($finder_details,'itemprop','postalCode');
                        $addressLocality = $this->getNodeValue($finder_details,'itemprop','addressLocality');
                        $estate = $this->getNodeValue($finder_details,'itemprop','addressRegion');

                        $look_for_district = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-address ')]")[0]->childNodes;
                        $district = "";
                        foreach ($look_for_district as $item){
                            if($item->nodeName == "#text"){
                                $district = trim($item->nodeValue);
                            }
                        }
                        $latitude = $this->getNodeValueWithExt($finder_details,'itemprop','latitude','/@content');
                        $longitude = $this->getNodeValueWithExt($finder_details,'itemprop','longitude','/@content');
                        $details = $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' additionalProperty ')]");

                        $details_array = new StdClass;
                        foreach ($details as $detail){
                            $detail_element = $dom_for_details->saveHTML($detail);
                            $attribute = $this->getElementValue($detail_element,'itemprop','name');
                            $value = $this->getElementValue($detail_element,'itemprop','value');
                            $details_array->{str_replace(' ','_',$attribute)} = $value;
                        }
                        $description =  $finder_details->query("//*[contains(concat(' ', normalize-space(@itemprop), ' '), ' description ')]")[0];
                        $buffer = array();
                        foreach ($description->childNodes as $node ){
                            if($node->nodeName === '#text' && trim(preg_replace("/\r\n/", '', $node->nodeValue)) !== '') {
                                $buffer[] = $node->nodeValue;
                            }
                        }

                        $agent = $this->getNodeValueWithExt($finder_details,'class','list-group-item-heading','//a');
                        $agencyName = $listed_by;
                        $agencyLicense = $this->getNodeValue($finder_details,'class','agent-license');
                        $contact = $this->getNodeValue($finder_details,'class','gallery-form__thankyou__agent-phone');

                        $projectInfo = $finder_details->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-details-primary ')]");
                        $projectInfoObject = new StdClass;
                        if(isset($projectInfo[1])){
                            $projectInfoElement = $dom_for_details->saveHTML($projectInfo[1]);
                            $projectInfos = $this->getElementChildNodes($projectInfoElement,'class','property-attr');

                            foreach ($projectInfos  as $info){
                                $attribute = $info->childNodes[1]->textContent;
                                $value = $info->childNodes[3]->textContent;
                                $projectInfoObject->{str_replace(' ','_',$attribute)} = $value;
                            }
                        }else{
                            $projectInfoObject = null;
                        }

                        $description_final = str_replace("\r",' ',trim(str_replace("\n",'',implode("\r\n", $buffer))));
                        $result_json = json_encode( array(
                                "property_name" => $property_name,
                                "price" => $price,
                                "streetAddress" => $streetAddress,
                                "postalCode" => $postalCode,
                                "addressLocality" => $addressLocality,
                                "district" => $district,
                                "estate" => $estate,
                                "latitude" => $latitude,
                                "longitude" => $longitude,
                                "details" => $details_array,
                                "projectInfo" => $projectInfoObject,
                                "description" => $description_final,
                                "agentName" => $agent,
                                "agencyName" => $agencyName,
                                "agencyLicense" => $agencyLicense,
                                "contact" => trim($contact)
                            )
                        );

                        $this->updateData($result_json);
                        break;
                    }

                    if($tries == 10){
                        sleep(600);
                    }

                    print("failed attempt! :" . $link ."\r\n");
                    $tries = $tries +1;
                }
            }

            $next_pagination_url = $this->getNodeValueWithExt($finder,'class','pagination-next','//a/@href');

            if ($next_pagination_url != ''){
                $url = 'https://www.propertyguru.com.sg'.$next_pagination_url;
            }else{
                break;
            }
        }

        return;
    }

    private function getNodeValue($xpath,$attribute, $value){
        $value = $xpath->query("//*[contains(concat(' ', normalize-space(@$attribute), ' '), ' $value ')]");
        return $value->length > 0 ? $value[0]->textContent : '';
    }
    private function getNodeValueWithExt($xpath,$attribute, $value,$ext){
        $value = $xpath->query("//*[contains(concat(' ', normalize-space(@$attribute), ' '), ' $value ')]$ext");
        return $value->length > 0 ? $value[0]->textContent : '';
    }

    private function getElementChildNodes($element, $attribute, $value){
        $dom = new DOMDocument();
        @$dom->loadHTML($element);

        $finder = new DOMXPath($dom);
        $result = $finder->query("//*[contains(concat(' ', normalize-space(@".$attribute."), ' '), ' $value ')]");

        return $result->length ? $result : [];
    }

//    private function getElementChildNodesWithExt($element, $attribute, $value,$ext){
//        $dom = new DOMDocument();
//        @$dom->loadHTML($element);
//
//        $finder = new DOMXPath($dom);
//        $result = $finder->query("//*[contains(concat(' ', normalize-space(@".$attribute."), ' '), ' $value ')]$ext");
//
//        return $result->length ? $result[0]->childNodes : [];
//    }

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

    public function saveSearch($search,$request){

        $tosearch = str_replace("'","",$search);
        $tosearch = preg_replace('/[^a-zA-Z0-9_ -]/s','',$tosearch);
        $torequest = str_replace("'","",$request);
        try{
            $exists = $this->connection->createCommand("SELECT * FROM xp_guru_requests WHERE search ='$tosearch'")->queryAll();
            if(!$exists){
                $this->connection->createCommand()
                    ->insert(
                        'xp_guru_requests_listings',
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
                        'xp_guru_pages_listings',
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

    public function updateData($json){

        $decoded_json = json_decode($json);
        try{

            $agent_name = preg_replace('/[^a-zA-Z0-9_ -]/s','',$decoded_json->agentName);
            $property_name = str_replace("'","\"", $decoded_json->property_name);
            $address = str_replace("'", "\"", $decoded_json->streetAddress);
            $price = str_replace("'","\"",$decoded_json->price);
            $agent_name = str_replace("'","\"",$agent_name);
            $contact = str_replace("'","\"",$decoded_json->contact);

            $exists = $this->connection->createCommand("SELECT * from xp_guru_listings where property_name= '$decoded_json->property_name' and property_address ='$address' and listed_by='$agent_name'")->queryAll();
            if(!$exists){
                $this->connection->createCommand()
                    ->insert(
                        'xp_guru_listings',
                        array(
                            'json' => str_replace("'","",$json),
                            'property_name' => $property_name,
                            'property_address' => $address,
                            'price' => $price,
                            'listed_by' =>  $agent_name,
                            'contact' => $contact
                        )
                    );
                print("inserted \r\n");
            }else{
                $this->connection->createCommand("UPDATE xp_guru_listings SET json ='$json',property_name='$decoded_json->property_name',property_address='$decoded_json->streetAddress',price='$decoded_json->price',listed_by='$agent_name',contact='$decoded_json->contact' WHERE property_name ='$decoded_json->property_name' and propety_address = '$decoded_json->streetAddress' and listed_by = '$agent_name'");
                print("updated \r\n");
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
            'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1',
            'Mozilla/5.0 (Linux; Android 8.0.0; SM-G960F Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.84 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 7.0; SM-G892A Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/60.0.3112.107 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 7.0; SM-G930VC Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/58.0.3029.83 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 6.0.1; SM-G935S Build/MMB29K; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/55.0.2883.91 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 6.0.1; SM-G920V Build/MMB29K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.98 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 5.1.1; SM-G928X Build/LMY47X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.83 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 6P Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.83 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 7.1.1; G8231 Build/41.2.A.0.219; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/59.0.3071.125 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 6.0.1; E6653 Build/32.2.A.0.253) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.98 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 6.0; HTC One X10 Build/MRA58K; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/61.0.3163.98 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 6.0; HTC One M9 Build/MRA58K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.98 Mobile Safari/537.3',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.59 Safari/537.36 115Browser/8.6.2',
            'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.108 Safari/537.36 2345Explorer/7.1.0.12633',
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.57 Safari/537.17 QIHU 360EE',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.152 Safari/537.36 QIHU 360SE',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome Safari/537.36',
            'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533+ (KHTML, like Gecko) Element Browser 5.0',
            'Mozilla/5.0 (Windows; U; Windows NT 6.1; cs-CZ) AppleWebKit/533+ (KHTML, like Gecko) Element Browser 6.0',
            'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.10) Gecko/20101026 Epic/1.2 Firefox/3.6.10',
            'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.9.2.7) Gecko/20100723 Epic/1.1 Firefox/3.6.7',
            'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.6) Gecko/20100702 Epic/1.0 Firefox/3.6.6',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/528+ (KHTML, like Gecko, Safari/528.0) Lunascape/5.0.2.0',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1b3pre) Gecko/2008 Lunascape/4.9.9.98',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.2) Gecko/20090804 Firefox/3.5.2 Lunascape/5.1.4.5',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.11) Gecko/20100821 Firefox/3.5.11 Lunascape/6.3.1.22729',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; ko; rv:1.9.2.16) Gecko/20110325 Firefox/3.6.16 Lunascape/6.4.5.23569',
            'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.28) Gecko/20120410 Firefox/3.6.28 Lunascape/6.7.1.25446'
        );

        if(is_callable( 'curl_init' )) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $agents[array_rand($agents)]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL , 1);
            curl_setopt($ch, CURLOPT_PROXY, $this->getProxy());
//            curl_setopt($ch, CURLOPT_PROXY, $proxyIP );
//            curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
//            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyUsername:$proxyPassword");
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

    public function skipData($name, $address, $listed_by){
        $value = false;
        try{
            $listed_by = preg_replace('/[^a-zA-Z0-9_ -]/s','',$listed_by);
            $listed_by = str_replace("'","\"",$listed_by);
            $address = str_replace("'","\"",$address);
            $exists  = $this->connection->createCommand("SELECT * FROM xp_guru_listings WHERE property_name = '$name' and property_address ='$address' and listed_by='$listed_by'")->queryAll();
            $value = count($exists)> 0 ? true : false;
        }
        catch(Throwable $e){
            print($e. "\r\n");
        }finally{

        }

        return $value;

    }
}
