<?php

namespace Vladitot\Architect\YamlComponents;

use Vladitot\Architect\AbstractGenerator;
use Vladitot\Architect\Yaml\Module;
use App\Models\GitlabChart;
use App\Models\GitlabChartDictionary;

class GitlabGenerator extends AbstractGenerator
{

    public function generate(Module $module)
    {
        $gitlabChart = GitlabChart::whereProjectId($this->projectId)->first();
        $gitlabChartFromDictionary = GitlabChartDictionary::whereId($gitlabChart->gitlab_chart_dictionary_id)->first();

        $fileBody = 'include:'."\n";

        $fileBody.="- project: '".$gitlabChartFromDictionary->repository."'"."\n";
        $fileBody.="  ref: ".$gitlabChart->version."\n";
        $fileBody.="  file: '".$gitlabChartFromDictionary->path."'"."\n";

        $variables = [];
        foreach (explode("\n", $gitlabChartFromDictionary->default_variables) as $variablePair) {
            $explodedPair = explode(":", $variablePair);
            $key = $explodedPair[0];
            $value = $explodedPair[1];
            $variables[$key] = $value;
        }

        foreach (explode("\n", $gitlabChart->variables) as $variablePair) {
            $explodedPair = explode(":", $variablePair);
            $key = $explodedPair[0];
            $value = $explodedPair[1];
            $variables[$key] = $value;
        }

        $fileBody.="variables:"."\n";
        foreach ($variables as $key=>$value) {
            $value = trim($value);
            $value = trim($value, '"');
            $value = '"'.$value.'"';
            $fileBody.="  ".$key.": ".$value."\n";
        }

        file_put_contents($this->projectBasePath."/.gitlab-ci.yml", $fileBody);
    }
}
