<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/05/2018
 * Time: 13:22
 */

namespace JCT;


use Exception;

class GeneratePDF
{
    public $charset = 'utf-8';
    public $default_font_family = 'Arial';
    public $page_size = 'A4';
    public $default_font_size = 8;

    public $list_auto_mode = 'browser';

    public $margin_left = 20;
    public $margin_right = 20;
    public $margin_top = 50;
    public $margin_bottom = 30;

    public $page_num_prefix = 'Page ';
    public $page_num_suffix = '';
    public $nbpg_prefix = ' of ';
    public $nbpg_suffix = '';

    private $default_css_path = '';

    public $css;
    private $template;
    private $pdf_obj;

    private $header = '';
    private $footer = '';
    private $content = '';

    function __construct()
    {
        $this->default_css_path = JCT_PATH_ASSETS . 'css' . JCT_DE . 'default_pdf.css';
        $this->css = file_get_contents($this->default_css_path);

        require_once JCT_PATH_CORE_VENDORS . 'mpdf' . JCT_DE . 'mpdf.php';
    }


    /**
     * @param $template
     *
     * Set the html for the document. Can be completed
     * HTML for an entire document or a HTML template for
     * a single page to be looped
     */
    function set_template($template)
    {
        $this->template = $template;
    }

    /**
     * @param array $str_replace_array
     * @param bool $set_page_break
     *
     * Takes the template and either
     * sets that as the finished content (i.e. when the template itself is
     *  the finished HTML for the entire document) or
     * can be used singly or in a loop to append HTML to the content based on
     *  the set template (i.e. when the template is the raw HTML for a single page)
     */
    function update_page_content($str_replace_array = [], $set_page_break = false)
    {
        $content = (!empty($str_replace_array)) ? strtr($this->template, $str_replace_array) : $this->template;
        if($set_page_break)
            $content.= '<pagebreak />';

        $this->content.= $content;
    }

    function set_document_header($header_html,$show_page_numbering=false,$show_page_total=false)
    {
        $page_numbering_str = '';
        if( $show_page_numbering || $show_page_total )
        {
            if($show_page_numbering)
                $page_numbering_str.= $this->page_num_prefix . '{PAGENO}' . $this->page_num_suffix;

            if($show_page_total)
                $page_numbering_str.= $this->nbpg_prefix . '{nbpg}' . $this->nbpg_suffix;


            $has_placeholder = (strpos($header_html,'$_PAGE_NUMBERING') !== false);
            if($has_placeholder)
                $header_html = str_replace('$_PAGE_NUMBERING', $page_numbering_str, $header_html);
            else
                $header_html.= '<p style="margin:0;text-align:right;">' . $page_numbering_str . '</p>';
        }

        $this->header = $header_html;
    }

    function set_document_footer($footer_html,$show_page_numbering=false,$show_page_total=false)
    {
        $page_numbering_str = '';
        if( $show_page_numbering || $show_page_total )
        {
            if($show_page_numbering)
                $page_numbering_str.= $this->page_num_prefix . '{PAGENO}' . $this->page_num_suffix;

            if($show_page_total)
                $page_numbering_str.= $this->nbpg_prefix . '{nbpg}' . $this->nbpg_suffix;


            $has_placeholder = (strpos($footer_html,'$_PAGE_NUMBERING') !== false);
            if($has_placeholder)
                $footer_html = str_replace('$_PAGE_NUMBERING', $page_numbering_str, $footer_html);
            else
                $footer_html.= '<p style="margin:0;text-align:right;">' . $page_numbering_str . '</p>';
        }

        $this->footer = $footer_html;
    }

    function generate_pdf($output_file_path)
    {
        try
        {
            if($this->template === null)
                throw new Exception('The Template has not been set.');

            if($this->content === '')
                throw new Exception('The Content has not been set.');

            $this->pdf_obj = new \mPDF();

            if($this->header !== '')
                $this->pdf_obj->setHTMLHeader($this->header);

            if($this->footer !== '')
                $this->pdf_obj->setHTMLFooter($this->footer);

            // string $mode,
            // mixed $format,
            // float $default_font_size,
            // string $default_font,
            // float $margin_left,
            // float $margin_right,
            // float $margin_top,
            // float $margin_bottom,
            // float $margin_header,
            // float $margin_footer,
            // string $orientation

            $this->pdf_obj->mPDF(
                $this->charset,
                $this->page_size,
                $this->default_font_size,
                $this->default_font_family,
                $this->margin_left,
                $this->margin_right,
                $this->margin_top,
                $this->margin_bottom
            );
            $this->pdf_obj->debug = true;

            $this->pdf_obj->list_auto_mode = $this->list_auto_mode;


            if($this->css !== null)
                $this->pdf_obj->writeHTML($this->css, 1);

            $this->pdf_obj->writeHTML($this->content, 2);
            $this->pdf_obj->output($output_file_path);

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>'PDF failed to generate: ' . $e->getMessage()];
        }
    }
}