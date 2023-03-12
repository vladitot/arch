<?php
namespace Vladitot\Architect;

use Illuminate\Support\Str;

class NamespaceAndPathGeneratorYaml
{
    public static function getProjectPathByTitle(string $projectTitle) {
        $projectTitle = str_replace('.','',
            str_replace(' ', '', ucfirst($projectTitle))
        );
        return base_path().'/Projects/'.$projectTitle;
    }

    private static function generateCustomPath(
        string $module, string $title, string $custom
    ) {
//now we supports only subdirectory. next time we will support remote repository with branch creation, etc.

        $module = str_replace('.','',
            str_replace(' ', '', ucfirst($module))
        );
        $title = str_replace('.','',
            str_replace(' ', '', ucfirst($title))
        );

        return base_path().'/Packages/'
            .$module.'/'.ucfirst($custom).'/'
            .$title.'.php';
    }

    private static function generateCustomNonPhpPath(
        string $moduleTitle, string $title, string $custom
    ) {
        $moduleTitle = str_replace('.','',
            str_replace(' ', '', ucfirst($moduleTitle))
        );
        $title = str_replace(' ', '', ucfirst($title));

        return base_path().'/Packages/'
            .$moduleTitle.'/'.ucfirst($custom).'/'
            .$title;
    }

    private static function generateCustomNamespace(
        string $moduleTitle, string $custom,
    ) {

        $moduleTitle = str_replace('.','',
            str_replace(' ', '', ucfirst($moduleTitle))
        );

        if ($moduleTitle==='/' || $moduleTitle==='') {
            return 'Packages\\'.ucfirst($custom);
        } else {
            return 'Packages\\' .$moduleTitle.'\\'.ucfirst($custom);
        }
    }

    public static function convertStringToSnakeCase(string $title) {
        return Str::snake($title);
    }
    public static function generateTableNameFromModelName(string $title) {

        $snakeTitle = self::convertStringToSnakeCase($title);

        return Str::pluralStudly($snakeTitle);

    }

    public static function generateModelNamespace(string $moduleTitle) {
        return self::generateCustomNamespace(
            $moduleTitle, 'Models'
        );
    }

    public static function generateMigrationNamespace(string $moduleTitle) {
        return self::generateCustomNamespace(
            $moduleTitle, 'Migrations'
        );
    }

    public static function generateModelPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'Models'
        );
    }

    public static function generateMigrationPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'Migrations'
        );
    }

    public static function generateFactoryPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'Factories'
        );
    }

    public static function generateFactoryNamespace(string $moduleTitle){
        return self::generateCustomNamespace(
            $moduleTitle, 'Factories'
        );
    }

    public static function generateRepositoryPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'Repositories'
        );
    }

    public static function generateRepositoryNamespace(string $moduleTitle){
        return self::generateCustomNamespace(
            $moduleTitle, 'Repositories'
        );
    }

    public static function generateServicePath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'Services'
        );
    }

    public static function generateServiceAggregatorPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ServiceAggregators'
        );
    }

    public static function generateDocsPath(string $moduleTitle, string $title) {
        return self::generateCustomNonPhpPath(
            $moduleTitle, $title, 'docs'
        );
    }

    public static function generateServiceNamespace(string $moduleTitle){
        return self::generateCustomNamespace(
            $moduleTitle, 'Services'
        );
    }

    public static function generateServiceAggregatorNamespace(string $moduleTitle){
        return self::generateCustomNamespace(
            $moduleTitle, 'ServiceAggregators'
        );
    }

    public static function generateControllerPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'Controllers'
        );
    }

    public static function generateRoutesHelperPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'RoutesHelpers'
        );
    }

    public static function generateControllerTestPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ControllerTests'
        );
    }

    public static function generateServiceTestPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ServiceTests'
        );
    }

    public static function generateServiceAggregatorTestPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ServiceAggregatorTests'
        );
    }

    public static function generateRepositoryTestPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'RepositoryTests'
        );
    }

    public static function generateRoutesPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'Routes'
        );
    }

    public static function generateControllerNamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'Controllers'
        );
    }

    public static function generateRoutesHelpersNamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'RoutesHelpers'
        );
    }

    public static function generateControllerTestNamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'ControllerTests'
        );
    }

    public static function generateServiceTestNamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'ServiceTests'
        );
    }

    public static function generateServiceAggregatorTestNamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'ServiceAggregatorTests'
        );
    }

    public static function generateRepositoryTestNamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'RepositoryTests'
        );
    }

    public static function generateControllerRequestPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ControllerRequests'
        );
    }


    public static function generateServiceProvidersPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ServiceProviders'
        );
    }

    public static function generateControllerRequestNamespace(string $moduleTitle){
        return self::generateCustomNamespace(
            $moduleTitle, 'ControllerRequests'
        );
    }

    public static function generateServiceProviderNamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'ServiceProviders'
        );
    }

    public static function generateControllerResourcePath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ControllerResource'
        );
    }

    public static function generateControllerResourceNamespace(string $moduleTitle){
        return self::generateCustomNamespace(
            $moduleTitle, 'ControllerResource'
        );
    }

    public static function generateRepositoryDTONamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'RepositoryDTO'
        );
    }

    public static function generateControllerDTONamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'ControllerDTO'
        );
    }



    public static function generateRepositoryDTOPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'RepositoryDTO'
        );
    }
    public static function generateServiceDTONamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'ServiceDTO'
        );
    }
    public static function generateServiceAggregatorDTONamespace(string $moduleTitle){
        return self::generateCustomNamespace($moduleTitle, 'ServiceAggregatorDTO'
        );
    }

    public static function generateServiceDTOPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ServiceAggregatorDTO'
        );
    }

    public static function generateServiceAggregatorDTOPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ServiceAggregatorDTO'
        );
    }
    public static function generateControllerDTOPath(string $moduleTitle, string $title) {
        return self::generateCustomPath(
            $moduleTitle, $title, 'ControllerDTO'
        );
    }

    public static function generateJSApiPath(string $moduleTitle, string $title) {
        return self::generateCustomNonPhpPath(
            $moduleTitle, $title, 'JsApi'
        );
    }
}
