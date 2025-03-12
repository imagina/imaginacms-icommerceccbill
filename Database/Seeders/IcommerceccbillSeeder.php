<?php

namespace Modules\Icommerceccbill\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Icommerce\Entities\PaymentMethod;

class IcommerceccbillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        Model::unguard();

        if(!is_module_enabled('Icommerceccbill')){
            //$this->command->alert("This module: Icommerceccbill is DISABLED!! , please enable the module and then run the seed");
            exit();
        }

        //Validation if the module has been installed before
        $name = config('asgard.icommerceccbill.config.paymentName');
        $result = PaymentMethod::where('name',$name)->first();

        if(!$result){

            $options['init'] = "Modules\Icommerceccbill\Http\Controllers\Api\IcommerceCcbillApiController";
            $options['mode'] = "sandbox";

            $options['accountNumber'] = null;
            $options['subAccountNumber'] = null;
            $options['flexFormId'] = null;
            $options['saltKey'] = null;

            $options['minimunAmount'] = 0;
            $options['maximumAmount'] = 300;

            $titleTrans = 'icommerceccbill::icommerceccbills.single';
            $descriptionTrans = 'icommerceccbill::icommerceccbills.description';

            $params = array(
              'name' => $name,
              'status' => 1,
              'options' => $options
            );
            $paymentMethod = PaymentMethod::create($params);

            $this->addTranslation($paymentMethod,'en',$titleTrans,$descriptionTrans);
            $this->addTranslation($paymentMethod,'es',$titleTrans,$descriptionTrans);

        }

    }


    /*
    * Add Translations
    * PD: New Alternative method due to problems with astronomic translatable
    **/
    public function addTranslation($paymentMethod,$locale,$title,$description){

      \DB::table('icommerce__payment_method_translations')->insert([
          'title' => trans($title,[],$locale),
          'description' => trans($description,[],$locale),
          'payment_method_id' => $paymentMethod->id,
          'locale' => $locale
      ]);

    }


}
