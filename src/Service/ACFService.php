<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Twig\Markup;

class ACFService extends AbstractController
{
    private $please;
    private $fieldsGroupedCount = 0;
    private $currentPropName;
    private $userServ;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->userServ = $this->please->serve('user');
    }

    public function getAcfRelatedTiles(array $acf, $tilesData, $item = null)
    {
        $controls = '';
        $tabs = '';

        preg_match_all("#([a-zA-Z]+\s*[a-zA-Z0-9,()_].*)#", attr($acf, 'fieldsList'), $matches, PREG_PATTERN_ORDER);

        if ($matches) {
            foreach ($matches[0] as $rowString) {
                preg_match_all("/([a-z_]+)(=)(\")(.*?)(\")/ui", $rowString, $matches1, PREG_PATTERN_ORDER);
                if ($matches1) {
                    foreach ($matches1[0] as $matchesString) {
                        preg_match_all("/([a-z_]+)(=)(\")(.*?)(\")/ui", $matchesString, $matches2, PREG_PATTERN_ORDER);
                        if ($matches2) {

                            foreach ($matches2[0] as $row) {
                                $row = explode('=', $row);
                                if (sizeof($row) == 2) {

                                    if ($row[0] == 'type') {

                                        if ($row[1] == '"fields_group"') {

                                            if ($this->fieldsGroupedCount > 0) {
                                                $controls .= '</div><!-- .row -->
                                                            </div><!-- .content -->
                                                        </div><!-- .title -->';
                                            }

                                            $groupTitle = $this->_getFieldsGroupTitle($matches1[0]);

                                            $slug = $this->please->serve('string')->getSlug($groupTitle);

                                            $tabs .= '<a href="#' . $slug . '" data-js="ACF={click:scrollToTab}">' . $groupTitle . '</a>';

                                            $controls .= '<div class="tile tile-acf" id="tab_' . $slug . '">
                                                            <header><h3 class="title">' . $groupTitle . '</h3></header>
                                                            <div class="content">
                                                                <div class="row">';

                                            $this->fieldsGroupedCount++;
                                        }
                                        $controls .= $this->_getACFFormControl($row[1], $matches1[0], $item, $tilesData);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $tabs = '<div class="tiles tiles-tab">
                    <div class="tile">' . $tabs . '</div>
                </div>';

        $controls .= '<script defer src="' . $this->please->serve('asset')->getCDN('swagg/sfm/acf.js?v=' . rand()) . '"></script>';

        return new Markup($tabs . $controls, 'UTF-8');
    }

    public function getAcf(?array $data, string $fieldsPath, $onNull = null, $isEmptiale = true)
    {
        $fieldsPath = "acf.$fieldsPath";
        return attr($data, $fieldsPath, $onNull, $isEmptiale);
    }

    private function _getFieldsGroupTitle($matches1)
    {
        foreach ($matches1 as $m) {
            if (strpos($m, 'label') !== false) {
                $trim = trim($m, 'label');
                return trim($trim, '="');
            }
        }
        return 'Groupe de champs';
    }

    private function _getACFFormControl($type, $rows, $item, $tilesData)
    {
        $fields = $this->_getFields($rows);

        $propName =
            empty($fields->acf->acf) && empty($fields->acf->acfName)
            ? $fields->acf->name
            : (!empty($fields->acf->acfName) ? $fields->acf->acfName : $fields->acf->acf);

        if (!isset($this->groupName)) {
            $this->groupName = $propName;
        }
        if ($fields->acf->type == 'fields_group') {
            $this->groupName = $fields->acf->name;
        }

        $value = $this->_getValue($item, $fields) ?? $fields->acf->value;

        $attrs = (object)[
            'clsNames' => (object)[
                'select' => 'class="gf-control form-control select2-basic gf-full"'
            ],
            'multiple' => $fields->acf->multiple == 'true' ? 'multiple="multiple"' : '',
            'dataSelectOption' => is_array($value) ? 'data-select-option="' . htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8') . '"' : 'data-select-option="' . $value . '"'
        ];

        /*$name = 'name="acf['. $this->groupName .']['. (!empty($fields->acf->acf) ? (strpos($fields->acf->name, 'name__')!==false ? $fields->acf->acf : $fields->acf->name ) : $fields->acf->name) .']'.(!empty($fields->acf->acf) && $fields->acf->acf_type=='checkbox'?'[]':'').'"';
        $em = $this->groupName . '.' . (!empty($fields->acf->acf) ? (strpos($fields->acf->name, 'name__')!==false ? $fields->acf->acf : $fields->acf->name ) : $fields->acf->name);*/

        // lets ensure retrocompatibility
        //$fields->acf->acf_type = $fields->acf->controlType !== '' ? $fields->acf->controlType : $fields->acf->acf_type;
        $nameRaw = 'acf[' . $this->groupName . '][' . $propName . ']' . (
            !empty($fields->acf->controlType) && $fields->acf->controlType == 'checkbox'
            ? '[]'
            : ($fields->acf->multiple == 'true' ? '[]' : '')
        );
        $name = 'name="' . $nameRaw . '"';

        $em = $this->groupName . '.' . $propName;

        switch (trim($type, '"')) {
            case 'text':
            case 'email':
            case 'number':
            case 'range':
            case 'date':
                $control = '<div data-col class="col-md-' . $fields->acf->col . '">
                    <div class="field field-' . $fields->acf->type . ' ' . (empty($value) ? 'has-empty-val' : '') . '">
                        <div class="gf-wrapper">
                            <input 
                                ' . $name . '
                                ' . ($fields->acf->required == 'true' ? 'required' : '') . '
                                class="gf-control form-control ' . ($fields->acf->type == 'date' ? 'gf-full' : '') . '"
                                type="' . $fields->acf->type . '" 
                                ' . ($fields->acf->min     !== '' ? 'min="' . $fields->acf->min . '"' : '') . '
                                ' . ($fields->acf->max     !== '' ? 'max="' . $fields->acf->max . '"' : '') . '
                                ' . ($fields->acf->step    !== '' ? 'step="' . $fields->acf->step . '"' : '') . '
                                ' . ($fields->acf->pattern !== '' ? 'pattern="' . $fields->acf->pattern . '"' : '') . '
                                value="' . $value . '"
                                data-js="ACF={keypress|keyup|paste|input:updateSiblingHiddenInput}"
                            >
                            ' . $this->_getHiddenInput($fields, $value) . '
                            <div class="gf-label">' . $fields->acf->label . '</div>
                            <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                        </div>
                        <em>' . $em . '</em>
                    </div>
                </div>';
                break;
            case 'year':
                $years = '';
                $min = $fields->acf->min ?? 1800;
                $max = (new \DateTime())->format('Y');
                for ($i = $max + 1; $i > $min - 1; $i--) {
                    $years .= '<option value="' . $i . '"  ' . ($i == $value ? 'selected' : '') . '>' . $i . '</option>';
                }

                $control = '<div data-col class="col-md-' . $fields->acf->col . '">
                    <div class="field field-' . $fields->acf->type . ' ' . (empty($value) ? 'has-empty-val' : '') . '">
                        <div class="gf-wrapper">
                            <select 
                                ' . $name . '
                                ' . ($fields->acf->required == 'true' ? 'required' : '') . '
                                class="gf-control form-control select2-basic ' . ($fields->acf->type == 'date' ? 'gf-full' : '') . '"
                                ' . $years . '
                            </select>
                            <div class="gf-label">' . $fields->acf->label . '</div>
                            <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                        </div>
                        <em>' . $em . '</em>
                    </div>
                </div>';
                break;
            case 'number-range':
                $numbers_range = '';
                $min = $fields->acf->min ?? 1;
                $max = $fields->acf->max ?? 10;
                if ($fields->acf->order == 'desc') {
                    for ($i = $max + 1; $i > $min - 1; $i--) {
                        $numbers_range .= '<option value="' . $i . '"  ' . ($i == $value ? 'selected' : '') . '>' . $i . '</option>';
                    }
                } else {
                    for ($i = $min - 1; $i < $max + 1; $i++) {
                        $numbers_range .= '<option value="' . $i . '"  ' . ($i == $value ? 'selected' : '') . '>' . $i . '</option>';
                    }
                }

                $control = '<div data-col class="col-md-' . $fields->acf->col . '">
                    <div class="field field-' . $fields->acf->type . ' ' . (empty($value) ? 'has-empty-val' : '') . '">
                        <div class="gf-wrapper">
                            <select 
                                ' . $name . '
                                ' . ($fields->acf->required == 'true' ? 'required' : '') . '
                                class="gf-control form-control select2-basic ' . ($fields->acf->type == 'date' ? 'gf-full' : '') . '"
                                ' . $numbers_range . '
                            </select>
                            <div class="gf-label">' . $fields->acf->label . '</div>
                            <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                        </div>
                        <em>' . $em . '</em>
                    </div>
                </div>';
                break;
            case 'duration':

                $control = '<div data-col class="col-md-' . $fields->acf->col . '"><div class="cell-container">';

                $format = explode(':', $fields->acf->format ?? null);
                $formatMax = explode(':', $fields->acf->max ?? null);
                $hours = $minutes = $seconds = $hoursInput = $minutesInput = $secondsInput = '';
                if ($format) {
                    if (in_array('h', $format)) {
                        $v = $value['h'] ?? '';
                        for ($i = 0; $i <= ($formatMax[0] ?? 24); $i++) {
                            $hours .= '<option value="' . $i . '"  ' . ($i == $v ? 'selected' : '') . '>' . $i . '</option>';
                        }
                        $control .= '<div class="field field-duration cell cell-33">
                                        <div class="gf-wrapper">
                                            <select name="acf[' . $this->groupName . '][' . $propName . '][h]" class="cell gf-control form-control select2-basic">' . $hours . '</select>
                                            <div class="gf-label">' . $fields->acf->label . ' (Heure)</div>
                                            <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                                        </div>
                                        <em>' . $em . '.h</em>
                                    </div>';
                    };
                    if (in_array('i', $format)) {
                        $v = $value['i'] ?? '';
                        for ($i = 0; $i <= ($formatMax[1] ?? 59); $i++) {
                            $i = $i < 10 ? ('0' . $i) : $i;
                            $minutes .= '<option value="' . $i . '"  ' . ($i == $v ? 'selected' : '') . '>' . $i . '</option>';
                        }
                        $control .= '<div class="field field-duration cell cell-33">
                                        <div class="gf-wrapper">
                                            <select name="acf[' . $this->groupName . '][' . $propName . '][i]" value="' . $v . '" class="cell gf-control form-control select2-basic">' . $minutes . '</select>
                                            <div class="gf-label">' . $fields->acf->label . ' (Minutes)</div>
                                            <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                                        </div>
                                        <em>' . $em . '.i</em>
                                    </div>';
                    };
                    if (in_array('s', $format)) {
                        $v = $value['s'] ?? '';
                        for ($i = 0; $i <= ($formatMax[2] ?? 59); $i++) {
                            $i = $i < 10 ? ('0' . $i) : $i;
                            $seconds .= '<option value="' . $i . '"  ' . ($i == $v ? 'selected' : '') . '>' . $i . '</option>';
                        }
                        $control .= '<div class="field field-duration cell cell-33">
                                        <div class="gf-wrapper">
                                            <select name="acf[' . $this->groupName . '][' . $propName . '][s]" value="' . $v . '" class="cell gf-control form-control select2-basic">' . $seconds . '</select>
                                            <div class="gf-label">' . $fields->acf->label . ' (Secondes)</div>
                                            <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                                        </div>
                                        <em>' . $em . '.s</em>
                                    </div>';
                    };
                }

                $control .= '</div></div>';

                break;
            case 'datetime':
                $control = '<div data-col class="col-md-' . $fields->acf->col . '">
                                <div class="field field-' . $fields->acf->type . ' ' . (empty($value) ? 'has-empty-val' : '') . '">
                                    <div class="gf-wrapper">
                                        <input 
                                            ' . $name . '
                                            ' . ($fields->acf->required == 'true' ? 'required' : '') . '
                                            class="gf-control form-control ' . ($fields->acf->type == 'date' ? 'gf-full' : '') . '"
                                            type="datetime-local" 
                                            ' . ($fields->acf->min     !== '' ? 'min="' . $fields->acf->min . '"' : '') . '
                                            ' . ($fields->acf->max     !== '' ? 'max="' . $fields->acf->max . '"' : '') . '
                                            ' . ($fields->acf->step    !== '' ? 'step="' . $fields->acf->step . '"' : '') . '
                                            ' . ($fields->acf->pattern !== '' ? 'pattern="' . $fields->acf->pattern . '"' : '') . '
                                            value="' . $value . '"
                                        >
                                        <div class="gf-label">' . $fields->acf->label . '</div>
                                        <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                                    </div>
                                    <em>' . $em . '</em>
                                </div>
                            </div>';
                break;
            case 'daterange':
                $start = $value['start'] ?? '';
                $end = $value['end'] ?? '';
                if ($start !== '') {
                    $v = (new \DateTime($start))->format('d/m/Y') . ' - ' . (new \DateTime($end))->format('d/m/Y');
                } else {
                    $v = '';
                }
                $control = '<div data-col class="col-md-' . $fields->acf->col . '">
                                <div class="field field-' . $fields->acf->type . ' ' . (empty($value) ? 'has-empty-val' : '') . '">
                                    <div class="gf-wrapper">
                                        <input 
                                            ' . ($fields->acf->required == 'true' ? 'required' : '') . '
                                            class="gf-control form-control daterange gf-full no-datajs"
                                            type="text" 
                                            ' . ($fields->acf->min     !== '' ? 'min="' . $fields->acf->min . '"' : '') . '
                                            ' . ($fields->acf->max     !== '' ? 'max="' . $fields->acf->max . '"' : '') . '
                                            ' . ($fields->acf->step    !== '' ? 'step="' . $fields->acf->step . '"' : '') . '
                                            ' . ($fields->acf->pattern !== '' ? 'pattern="' . $fields->acf->pattern . '"' : '') . '
                                            value="' . $v . '"
                                        >
                                        <input name="acf[' . $this->groupName . '][' . $propName . '][start]" value="' . $start . '" type="hidden">
                                        <input name="acf[' . $this->groupName . '][' . $propName . '][end]" value="' . $end . '" type="hidden">
                                        <div class="gf-label">' . $fields->acf->label . '</div>
                                        <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                                    </div>
                                    <em>' . $em . '</em>
                                </div>
                            </div>
                            <script>
                                AddScript(function(){
                                    "use strict";
                                    $.map($(\'.daterange\'), function(el){
                                        $(el).daterangepicker({
                                            language: "fr-FR",
                                            autoUpdateInput: false,
                                            locale: {
                                                cancelLabel: "clear"
                                            },
                                            minYear: "' . (new \DateTime())->format('Y') . '",
                                            //minDate: "' . (new \DateTime())->format('d/m/Y') . '",

                                        });
                                        $(el).on("apply.daterangepicker", function(ev, picker){
                                            $(this).val(picker.startDate.format("MM/DD/YYYY")+ " - " +picker.endDate.format("MM/DD/YYYY")).addClass("gf-full")
                                                   .find("~.start").val(picker.startDate.format("YYYY-MM-DD")).end()
                                                   .find("~.end").val(picker.endDate.format("YYYY-MM-DD")).end();
                                        }).on("cancel.daterangepicker", function(){
                                            $(this).val("")
                                                   .find("~.start").val("").end()
                                                   .find("~.end").val("").end();
                                        })
                                    })
                                    
                                })
                            </script>';
                break;
            case 'textarea':
                $control = '<div data-col class="col-md-' . $fields->acf->col . '">
                                <div class="field field-' . $fields->acf->type . ' ' . (empty($value) ? 'has-empty-val' : '') . '">
                                    <div class="gf-wrapper">
                                        <textarea 
                                            ' . $name . '
                                            ' . ($fields->acf->required == 'true' ? 'required' : '') . '
                                            class="gf-control form-control"
                                            min="' . $fields->acf->min . '" 
                                            max="' . $fields->acf->max . '" 
                                            type="' . $fields->acf->type . '" 
                                            data-js="ACF={keypress|keyup|paste|input:updateSiblingHiddenInput}"
                                        >' . $value . '</textarea>
                                        ' . $this->_getHiddenInput($fields, $value) . '
                                        <div class="gf-label">' . $fields->acf->label . '</div>
                                        <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                                    </div>
                                    <em>' . $em . '</em>
                                </div>
                            </div>';
                break;
            case 'text-rich':
                $control = '
                    <div data-col class="col-md-' . $fields->acf->col . '">
                        <div class="field field-text-rich">
                            <label>' . $fields->acf->label . '</label>
                            <textarea class="trumbowyg" ' . ($fields->acf->required == 'true' ? 'required' : '') . ' ' . $name . ' >' . $value . '</textarea>
                        </div>
                        <em>' . $em . '</em>
                    </div>
                    <style>
                        body.trumbowyg-body-fullscreen .main--header, 
                        body.trumbowyg-body-fullscreen .main--footer 
                        {display:none}
                    </style>
                    <script>
                    AddScript(function(){
                        __.trumbowyg({
                            el: $(".trumbowyg")
                        });
                    })
                    </script>
                ';
                break;
            case 'page-builder':
                $builderId = 'builder_wrapper_' . uniqid();
                $control = '
                    <div data-col class="col-md-' . $fields->acf->col . '">
                        <div class="field field-page-builder">
                            <label>' . $fields->acf->label . '</label>
                            <div id="' . $builderId . '" sg-persist-html="true" name="' . $nameRaw . '">
                                ' . ($value ?: '<div sg-text-rich>Je suis le corps de cette page.</div>') . '
                            </div>
                        </div>
                        <em>' . $em . '</em>
                    </div>
                    <script>
                    AddScript(function(){
                        "use strict";
                        Builder.init({
                            swaggSelector: "#' . $builderId . '",
                            actionsMargin: "120px 15px",
                            buildPersistContentData: function(data){
				                var $form = $("<form />")
                                .append($("<input />").attr({ name: "sections" }).val(data.sections))
                                .append($("<input />").attr({ name: data.$swagg.attr("name") }).val(data.cleanedDOM));
                                return $form.serialize();
                            }
                        })
                    })
                    </script>
                ';
                break;
            case 'image':
            case 'file':
                $exploded = explode('.', $value);
                $val = htmlspecialchars($value);
                //$titleAttr = $val != '' ? 'title="'.$val.'"': '';
                $control = '
                    <div data-col class="col-md-' . $fields->acf->col . '">
                        <div class="field field-image">
                            <div
                                data-fg data-fg-name="acf[' . $this->groupName . '][' . htmlspecialchars($fields->acf->name) . ']"
                                data-fg-attachment="contain"
                                data-fg-src="' . $value . '"
                            ></div>
                            <label>' . $fields->acf->label . '</label>
                        </div>
                        <em>' . $em . '</em>
                    </div>
                ';
                // $control = '
                //     <div data-col class="col-md-'. $fields->acf->col .'" title="'.$val.'">
                //         <div class="field field-image" data-is-image="true">
                //             <input data-onpageloaded="click" 
                //                 data-js="sfmACF={click:fileControl__previewPicked}" type="hidden"
                //                 data-info='. json_encode([ '_extension' => end($exploded), '_extendedFilename' => $value ]) .'
                //                 name="acf['. $this->groupName .']['. htmlspecialchars($fields->acf->name) .']" 
                //                 value="'.$val.'" />
                //             <label>'. $fields->acf->label .'</label>
                //             <div data-js="sfmACF={click:fileControl__getLibrary}" class="widget-preview-area is-empty">
                //                 <a data-js="sfmACF={click:fileControl__removeFile}" title="Retirer"><i class="fa fa-trash"></i></a>
                //             </div>
                //         </div>
                //         <em>'.$em.'</em>
                //     </div>
                // ';
                break;
            case 'icon':
                $control = '
                    <div data-col class="col-md-' . $fields->acf->col . '">
                        <div class="field field-icon">
                            <input data-onpageloaded="click" 
                                data-js="ACF={click:iconControl___previewPicked}" 
                                type="hidden" 
                                name="acf[' . $this->groupName . '][' . htmlspecialchars($fields->acf->name) . ']" 
                                value="' . htmlspecialchars($value) . '" />
                            <label>' . $fields->acf->label . '</label>
                            <div data-js="ACF={click:iconControl__getLibrary}" class="widget-preview-area is-empty">
                                <a data-js="ACF={click:iconControl__removeIcon}" title="Retirer"><i class="fa fa-trash"></i></a>
                            </div>
                        </div>
                        <em>' . $em . '</em>
                    </div>
                ';
                break;
            case 'bloggy':

                $view = '';

                $bloggyCheckboxes = $bloggyRadios = $hiddenInput = '';

                if (!empty($fields->acf->bloggyType)) {

                    $criteria = [];
                    $bloggyTypes = explode('|', $fields->acf->bloggyType . '|message');

                    if ($bloggyTypes) {

                        $bloggies = collection('*')
                            ->whereIn('coll_name', $bloggyTypes)
                            ->where('published', '=', 'on')
                            ->orderBy('created_at', 'DESC')
                            ->limit(9999)
                            ->fetchAll();

                        if ($bloggies) {
                            switch ($fields->acf->controlType) {
                                case 'select':

                                    $view = '<select
                                            ' . $name . ' ' . $attrs->clsNames->select . ' ' . $attrs->dataSelectOption . ' ' . $attrs->multiple . '
                                            onchange=" $(this).parents(\'.gf-wrapper\').find(\'[name=parent]\').val( $(this).val() ).attr(\'value\', $(this).val()) "
                                            data-js="ACF={change:updateSiblingHiddenInput}"
                                        >';

                                    if ($fields->acf->required != 'true') {
                                        $view .= '<option value>AUCUN</option><option disabled></option>';
                                    }

                                    if ($bloggies) {
                                        $view .= $this->_getOptionsTree($bloggies, null, 0, null);
                                    }
                                    /*foreach($bloggies as $j => $bloggy){
                                            //$view .= '<option value="'.attr($bloggy, 'id').'">[[[ '.attr($bloggy, 'type').' ]]] -- '.attr($bloggy, 'title', attr($bloggy, '_acfTitle')).'</option>';
                                            $view .= '<option value="'.attr($bloggy, 'id').'">'.attr($bloggy, 'title', attr($bloggy, '_acfTitle')).'</option>';
                                            if( $j == count($bloggies)-1 ){
                                                $view .= '</select>';
                                            }
                                        }*/
                                    $view .= '</select>';
                                    break;
                                case 'radio':
                                case 'checkbox':
                                    $isCheckbox = ($fields->acf->controlType == 'checkbox' && is_array($value));
                                    foreach ($bloggies as $bloggy) {
                                        $id = $bloggy['id'];
                                        $view .= '<div class="pretty p-switch p-outline">
                                                <input ' . $name . ' type="' . $fields->acf->controlType . '" value="' . $id . '" ' . ($isCheckbox ? (in_array($id, $value) ? 'checked' : '') : ($value == $id ? 'checked' : '')) . '>
                                                    <div class="state">
                                                    <label>' . attr($bloggy, 'title', attr($bloggy, '_acfTitle')) . '</label>
                                                </div>
                                            </div>';
                                    }
                                    break;

                                default:
                                    $view = "<p class='alert alert-danger'>Le type de champ suivant: {$fields->acf->controlType} est inconnu.</p>";
                                    break;
                            }
                        } else {
                            $view = '<select ' . $name . ' ' . $attrs->clsNames->select . ' ' . $attrs->dataSelectOption . ' ' . $attrs->multiple . '>
                                            <option value>AUCUN</option><option disabled></option>
                                        </select>';
                        }
                    }
                } else {
                    $view = "<p class='alert alert-danger'>Le type de champ suivant: {$fields->acf->bloggyType} est inconnu.</p>";
                }

                $control = '
                    <div data-col class="col-md-' . $fields->acf->col . '">
                        <div class="field field-bloggy field-bloggy-' . $fields->acf->controlType . '">
                            <div class="gf-wrapper">
                                ' . $view . '
                                ' . $this->_getHiddenInput($fields, $value) . '
                                <div class="gf-label">' . $fields->acf->label . '</div>
                                <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs hidden"><i class="fa fa-times"></i></button>
                            </div>
                            <em>' . $em . '</em>
                        </div>
                    </div>
                ';
                break;
            case 'country':
                $control = '
                    <div data-col class="col-md-' . $fields->acf->col . '">
                        <div class="field field-country">
                            <div class="gf-wrapper">
                                <select ' . $name . ' ' . $attrs->clsNames->select . ' ' . $attrs->dataSelectOption . ' ' . $attrs->multiple . '>
                                ' . $this->please->serve('mix')->getCountriesOptions() . '
                                </select>
                                <div class="gf-label">' . $fields->acf->label . '</div>
                                <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                            </div>
                            <em>' . $em . '</em>
                        </div>
                    </div>
                ';
                break;
            case 'user':

                $roles = $fields->acf->roles;
                $users = [];

                if ($roles == '' || $roles == '*') {
                    $users = collection('users')->orderBy('created_at', 'DESC')->fetchAll();
                } else {
                    $users = [];
                    $roles = explode('|', $roles);
                    foreach ($roles as $role) {
                        $users = array_merge($users, collection('users')
                            ->where('roles', 'LIKE', '%"' . $role . '"%')
                            ->orderBy('created_at', 'DESC')
                            ->final());
                    }
                }

                $view = '<select data-js="ACF={change:updateSiblingHiddenInput}" ' . $name . ' ' . $attrs->clsNames->select . ' ' . $attrs->dataSelectOption . ' ' . ($fields->acf->required == 'true' ? 'required' : '') . ' ' . $attrs->multiple . '>';
                foreach ($users as $user) {
                    $id = $user['id'];
                    $view .= '<option ' . ($id == $value ? 'selected' : '') . ' value="' . $id . '">';
                    $view .= $this->userServ->getUserFullName($user) . ' (' . strtoupper($user['id']) . ')';
                    $view .= '</option>';
                };
                $view .= '</select>';

                if ($user = collection('users')->find($value)) {
                    $hiddenInput = $this->_getHiddenInput($fields, $this->userServ->getUserFullName($user) . " ({$user['id']})");
                }

                $control = '
                    <div data-col class="col-md-' . $fields->acf->col . '">
                        <div class="field field-users">
                            <div class="gf-wrapper">
                                ' . $view . '
                                ' . ($hiddenInput ?? '') . '
                                <div class="gf-label">' . $fields->acf->label . '</div>
                                <button data-js="ACF={click:emptyFieldValue}" type="button" class="btn-reset btn btn-xs"><i class="fa fa-times"></i></button>
                            </div>
                            <em>' . $em . '</em>
                        </div>
                    </div>';
                break;
            case 'infinity':
                /*
                type="fields_group" label="Vidéos" name="videos"
                    type="infinity" col="4" content="[type={file} label={Capture} name={p}, type={text} label={Description} name={d}, type={text} label={ID de la Vidéo} name={id}]"
                */
                $control = '';
                break;
            default:
                return '';
                break;
        }
        return $control ?? '';
    }

    private function _getFields($rows)
    {
        foreach ($rows as $row) {
            $row = explode('=', $row);
            if (sizeof($row) == 2) {
                $left_hand = $row[0];
                $right_hand = $row[1];
                switch ($left_hand) {
                    case 'type':
                        $type           = $right_hand;
                        break;
                    case 'name':
                        $name           = $right_hand;
                        break;
                    case 'label':
                        $label          = $right_hand;
                        break;
                    case 'min':
                        $min            = $right_hand;
                        break;
                    case 'max':
                        $max            = $right_hand;
                        break;
                    case 'rows':
                        $rows_          = $right_hand;
                        break;
                    case 'col':
                        $col            = $right_hand;
                        break;
                    case 'required':
                        $required       = $right_hand;
                        break;
                    case 'value':
                        $value          = $right_hand;
                        break;
                    case 'controlType':
                        $controlType    = $right_hand;
                        break;
                    case 'nullable':
                        $nullable       = $right_hand;
                        break;
                    case 'multiple':
                        $multiple       = $right_hand;
                        break;
                    case 'bloggyType':
                        $bloggyType     = $right_hand;
                        break;
                    case 'roles':
                        $roles          = $right_hand;
                        break;
                    case 'pattern':
                        $pattern        = $right_hand;
                        break;
                    case 'step':
                        $step           = $right_hand;
                        break;
                    case 'format':
                        $format         = $right_hand;
                        break;
                    case 'order':
                        $order          = $right_hand;
                        break;
                    case 'content':
                        $content        = $right_hand;
                        break;
                    case 'parent':
                        $parent         = $right_hand;
                        break;
                    case '_parent':
                        $_parent        = $right_hand;
                        break;
                    case '_title':
                        $_title         = $right_hand;
                        break;
                    case '_description':
                        $_description   = $right_hand;
                        break;
                    case 'parentChildren':
                        $parentChildren   = $right_hand;
                        break;
                    default:
                        break;
                }
            }
        }
        $label = trim($label ?? 'Label', '"');
        return (object)[
            'acf' => (object)[
                'type' => trim($type ?? 'text', '"'),
                'name' => trim($name ?? $this->please->serve('string')->getSlug($label, '_') ?? 'name__' . uniqid(), '"'),
                'label' => $label,
                'min' => trim($min ?? 0, '"'),
                'max' => trim($max ?? '', '"'),
                'rows' => trim($rows_ ?? 5, '"'),
                'col' => trim($col ?? 12, '"'),
                'required' => trim($required ?? false, '"'),
                //'acf' => trim($acf ?? false, '"'),
                //'acf_type' => trim($acf_type ?? false, '"'),
                //'acfName' => trim($acfName ?? false, '"'),
                'controlType' => trim($controlType ?? false, '"'),
                'value' => trim($value ?? false, '"'),
                'nullable' => trim($nullable ?? false, '"'),
                'multiple' => trim($multiple ?? false, '"'),
                'bloggyType' => trim($bloggyType ?? false, '"'),
                'roles' => trim($roles ?? false, '"'),
                'pattern' => trim($pattern ?? false, '"'),
                'step' => trim($step ?? false, '"'),
                'format' => trim($format ?? false, '"'),
                'order' => trim($order ?? false, '"'),
                'content' => trim($content ?? false, '"'),
                'parent' => trim($parent ?? false, '"'),
                '_parent' => trim($_parent ?? false, '"'),
                '_title' => trim($_title ?? false, '"'),
                '_description' => trim($_description ?? false, '"'),
                'parentChildren' => trim($parentChildren ?? false, '"'),
            ]
        ];
    }

    private function _getValue($item, $fields)
    {
        $acf = attr($fields, 'acf');
        if (attr($acf, 'type') == 'fields_group') {
            $this->currentPropName = attr($acf, 'name');
        } else {
            return attr($item, 'acf.' . $this->currentPropName . '.' . attr($fields, 'acf.name'));
        }
        return null;
    }

    protected function _getOptionsTree($collection, $parentid, $depth, $preventId)
    {
        $html = '';
        foreach ($collection as $collectId => $collect) {

            $parent = attr($collect, 'parent');
            $id = (int)attr($collect, 'id');
            $title = attr($collect, 'title', attr($collect, '_acfTitle'));

            if ((int)$preventId !== $id && (int)$parent == (int)$parentid || $collect) {
                $html .= '<option value="' . $id . '">';
                $html .= str_repeat("--", $depth);
                $html .= $title . " ($id)";
                //$html .= $this->_getOptionsTree($collection, $id, $depth+1, $preventId);
                $html .= '</option>';
            }
        }
        return $html;
    }

    private function _getHiddenInput($fields, $value)
    {
        if ($fields->acf->parent == "true" || $fields->acf->_parent == "true") {
            $hiddenInput = '<input type="hidden" name="parent" value="' . $value . '">';
        } else if ($fields->acf->_title == "true") {
            $hiddenInput = '<input type="hidden" name="title" value="' . $value . '">';
        } else if ($fields->acf->_description == "true") {
            $hiddenInput = '<input type="hidden" name="description" value="' . $value . '">';
        }
        return $hiddenInput ?? '';
    }
}
