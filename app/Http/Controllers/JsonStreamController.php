<?php

namespace App\Http\Controllers;

use App\Name;
use Illuminate\Http\Request;
use Stichoza\GoogleTranslate\GoogleTranslate;
use DB;

class JsonStreamController extends Controller
{
    protected $names;

    protected $translatedNames;

    public function stream()
    {
        $samplefile = storage_path('sample.json');

        /*
        * JSON streaming is an efficient, fast JSON stream parser based on generators developed for unpredictably long JSON streams or documents.
        */

        $jsonStream = \JsonMachine\JsonMachine::fromFile($samplefile);

        foreach ($jsonStream as $name => $data) {
            $translatedNames = $this->translate($data);
        }
    }

    public function translate($data)
    {
        $names = explode(",",trim($data['names'],'[]'));

        $hits = explode(",",trim($data['hits'],'[]'));

        $tr = new GoogleTranslate();

        foreach($names as $key => $name){
            $selectedName = DB::table('names')->where('name', $name)->first();

            $nameId = DB::table('names')->insertGetId(
                ['name' => $name, 'hit' => $hits[$key]]
            );

            // if duplicate
            if($selectedName){
                DB::table('names')
                    ->where('translated_name_id', $selectedName->id)
                    ->increment('hit', 1);
            } else {
                $hit = $hits[$key]++; 

                //Detect language
                $tr->translate($name);
    
                $this->translateAndSave($tr, $name, $hit, $nameId);
            }
        }

        return "Data translated and Save to DB";
    }

    public function translateAndSave($tr, $name, $hit, $nameId, $targetLang)
    {
        if($tr->getLastDetectedSource() == 'en'){
            $translatedName = $tr->setSource('en')->setTarget('ar')->translate($name);
        } else {
            $translatedName = $tr->setSource('ar')->setTarget('en')->translate($name);
        }

        DB::table('names')->insert(
            ['name' => $translatedName, 'hit' => $hit, 'translated_name_id' => $nameId]
        );
    }
}
