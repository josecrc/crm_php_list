<?php
/**
 * FormBuilderPlugin for phpList.
 *
 * Highly functional and elegant form builder to design signup forms,
 * map attributes, select subscription lists, and embed forms easily.
 *
 * @category  phplist
 * @author    Antigravity AI
 * @copyright 2026 Antigravity AI
 */

class FormBuilderPlugin extends phplistPlugin
{
    public $name = 'Form Builder';
    public $enabled = true;
    public $authors = 'Antigravity AI';
    public $description = 'A visual form builder that creates responsive signup forms and saves contacts to selected lists.';
    public $documentationUrl = '';
    public $priority = 100;
    
    // Public pages: ?pi=FormBuilderPlugin&p=view&id=X or ?pi=FormBuilderPlugin&p=submit
    public $publicPages = array('view', 'submit');

    // Menu settings inside dashboard
    public $topMenuLinks = array(
        'main' => array('category' => 'system'),
    );

    public $pageTitles = array(
        'main' => 'Form Builder',
        'edit' => 'Visual Form Builder',
    );

    // Database structure definition
    public $DBstruct = array(
        'forms' => array(
            'id' => array('integer not null primary key auto_increment', 'Form ID'),
            'name' => array('varchar(255) not null', 'Form Name'),
            'title' => array('varchar(255)', 'Form Title'),
            'description' => array('text', 'Form Description'),
            'lists' => array('text', 'Target Lists (comma separated IDs)'),
            'fields' => array('text', 'Form Fields JSON structure'),
            'styles' => array('text', 'Form Custom CSS or styles JSON'),
            'success_message' => array('text', 'Form success message'),
            'redirect_url' => array('text', 'Redirection thank you URL'),
            'created_at' => array('datetime', 'Created Date'),
        )
    );

    public function __construct()
    {
        $this->coderoot = dirname(__FILE__) . '/' . __CLASS__ . '/';
        parent::__construct();
        $this->version = '1.0.0';
    }

    public function activate()
    {
        parent::activate();
        
        // Initialize/create the database tables defined in $DBstruct if not exists
        $this->initialise();
    }

    public function adminmenu()
    {
        // This makes sure the plugin appears in the main menus under its registered category
        return array();
    }

    public function dependencyCheck()
    {
        return array(
            'PHP version 7.2 or greater' => version_compare(PHP_VERSION, '7.2') >= 0,
            'phpList version 3.0.0 or later' => version_compare(VERSION, '3.0.0') >= 0,
        );
    }
}
