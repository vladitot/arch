<?php

namespace Vladitot\Architect\YamlComponents;

use Vladitot\Architect\AbstractGenerator;
use Vladitot\Architect\NamespaceAndPathGeneratorYaml;
use Vladitot\Architect\Yaml\Laravel\AggregatorMethod;
use Vladitot\Architect\Yaml\Laravel\InputParam;
use Vladitot\Architect\Yaml\Laravel\OutputParam;
use Vladitot\Architect\Yaml\Module;

class JsApiClientGenerator extends AbstractGenerator
{

    public function generate(Module $module)
    {
        $serviceAggregators = $module->service_aggregators;

        foreach ($serviceAggregators as $serviceAggregator) {
            foreach ($serviceAggregator->methods as $method) {
                if (!isset($method->controller_fields)) continue;
                $this->generateJsApiForMethod($method, $module, $serviceAggregator->title);
            }
        }
    }

    private const phpToJsType = [
        'string'=>'string',
        'bool'=>'boolean',
        'int'=>'bigint',
        'float'=>'number',
    ];

    private function generateInputFieldsRecursively(InputParam $inputParam, string $tabs="\t"): string {
        $resultString = $tabs.$inputParam->title.': ';
        if (count($inputParam->childrenParams)===0) {
            $resultString.=self::phpToJsType[$inputParam->type].",\n";
        } else {
            foreach ($inputParam->childrenParams as $child) {
                $resultString.="[{\n";
                $tabs.="\t";
                $resultString.=$this->generateInputFieldsRecursively($child, $tabs);
                $resultString.="}],\n";
            }
        }
        return $resultString;
    }
    private function generateOutputFieldsRecursively(OutputParam $outputParam, string $tabs="\t"): string {
        $resultString = $tabs.$outputParam->title.': ';
        if (count($outputParam->childrenParams)===0) {
            $resultString.=self::phpToJsType[$outputParam->type].",\n";
        } else {
            foreach ($outputParam->childrenParams as $child) {
                $resultString.="[{\n";
                $tabs.="\t";
                $resultString.=$this->generateOutputFieldsRecursively($child, $tabs);
                $resultString.="}],\n";
            }
        }
        return $resultString;
    }

    private function generateJsApiForMethod(AggregatorMethod $method, Module $module, string $serviceAggregatorTitle)
    {
        $apiFilePath = NamespaceAndPathGeneratorYaml::generateJSApiPath(
            $module->title,
            $serviceAggregatorTitle.ucfirst($method->title).'Api.ts',
        );

        $fileBody = 'import {api} from "../../../аpi";'."\n".'import {ApiResponse} from "apisauce";'."\n";

        $fileBody.= 'export interface '.ucfirst($method->title).'Request {'."\n";
        foreach ($method->inputParams as $param) {
//            if (count($param->childrenParams)>0) {
                $fileBody.=$this->generateInputFieldsRecursively($param);
//            }
        }
        $fileBody.= '}'."\n\n";

        $fileBody.= 'export interface '.ucfirst($method->title).'Response {'."\n";
        $fileBody.= "\tresult: {"."\n";
        foreach ($method->outputParams as $param) {
//            if ($param->ddv1ParentParam===null) {
                $fileBody.=$this->generateOutputFieldsRecursively($param, "\t\t");
//            }
        }
        $fileBody.= "\t},"."\n";

        $fileBody.="\terror: {\n";
        $fileBody.="\t\tcode: bigint,\n";
        $fileBody.="\t\tmessage: string,\n";
        $fileBody.="\t\tpayload: string\n";
        $fileBody.="\t}\n";
        $fileBody.= '}'."\n\n";


        $fileBody.='export function '.ucfirst($method->title).' (request: '.ucfirst($method->title).'Request, headers: object): Promise<ApiResponse<'.ucfirst($method->title).'Response>> {'."\n";
        $fileBody.="\treturn api.".mb_strtolower($method->controller_fields->http_method)."('".$method->controller_fields->route."', request, { headers: headers });\n";
        $fileBody.="}\n\n";

        @mkdir(dirname($apiFilePath), recursive: true);
        file_put_contents($apiFilePath, $fileBody);

    }
}
