<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Twig\Markup;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class MixService extends AbstractController
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function getIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public function getRef($length = 5)
    {
        return substr(md5(substr(uniqid(''), 0, 20)), 0, $length);
    }


    public function sanitizeFilterCriteria(array $criteria): array
    {
        $result = [];

        $criteria = array_filter($criteria, function ($item) {
            return !in_array('page', $item, true);
        });

        foreach ($criteria as [$key, $operator, $values]) {
            // Initialiser si nécessaire
            if (!isset($result[$key])) {
                // Si c'est la première occurrence, on garde la structure exacte
                $result[$key] = [$key, $operator, $values];
            } else {
                // Fusionner les valeurs existantes avec les nouvelles
                $existingValues = $result[$key][2];
                $newValues = $values;

                if (is_array($existingValues) && is_array($newValues)) {
                    // Cas 1: Les deux sont des tableaux -> fusion
                    $merged = $existingValues;
                    foreach ($newValues as $value) {
                        if (!in_array((string)$value, array_map('strval', $merged))) {
                            $merged[] = $value;
                        }
                    }
                    $result[$key][2] = $merged;
                } elseif (!is_array($existingValues) && !is_array($newValues)) {
                    // Cas 2: Les deux sont des valeurs uniques
                    if ((string)$existingValues !== (string)$newValues) {
                        // Si différentes, on les transforme en tableau avec les deux valeurs
                        $result[$key][2] = [$existingValues, $newValues];
                    }
                    // Si identiques, on ne change rien
                } elseif (is_array($existingValues) && !is_array($newValues)) {
                    // Cas 3: Existant est tableau, nouveau est valeur unique
                    if (!in_array((string)$newValues, array_map('strval', $existingValues))) {
                        $existingValues[] = $newValues;
                        $result[$key][2] = $existingValues;
                    }
                } elseif (!is_array($existingValues) && is_array($newValues)) {
                    // Cas 4: Existant est valeur unique, nouveau est tableau
                    $merged = [$existingValues];
                    foreach ($newValues as $value) {
                        if (
                            (string)$value !== (string)$existingValues &&
                            !in_array((string)$value, array_map('strval', $merged))
                        ) {
                            $merged[] = $value;
                        }
                    }
                    $result[$key][2] = $merged;
                }
            }
        }

        $result = array_values($result);

        $filtered = array_values(array_filter($result, function ($item) {
            return !(isset($item[2]) && is_array($item[2]) && in_array('all', $item[2]));
        }));

        return $filtered;
    }

    public function allKeysExist(array $keys, array $array): bool
    {
        return !array_diff_key(array_flip($keys), $array);
    }

    public function atLeastOneKeyExist(array $keys, array $array): bool
    {
        return (bool) array_intersect($keys, array_keys($array));
    }

    public function atLeastOneValueExist(array $values, array $array): bool
    {
        return !empty(array_intersect($values, $array));
    }

    public function arrayMergeRecursiveEx(array $array1, array $array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursiveEx($merged[$key], $value);
            } else if (is_numeric($key)) {
                if (!in_array($value, $merged)) {
                    $merged[] = $value;
                }
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    public function randomify($list, $limit = null)
    {
        $randomified = [];
        foreach ($list as $each) {
            $randomified[rand()] = $each;
        }
        ksort($randomified);
        return array_slice($randomified, 0, $limit ?? count($randomified));
    }

    public function getCountriesOptions($selected = "Côte d'Ivoire", $countries = null)
    {
        $countries = $countries ?? ["Afghanistan", "Afrique du Sud", "Albanie", "Algerie", "Allemagne", "Andorre", "Angola", "Antigua-et-Barbuda", "Arabie saoudite", "Argentine", "Armenie", "Australie", "Autriche", "Azerbaidjan", "Bahamas", "Bahrein", "Bangladesh", "Barbade", "Belau", "Belgique", "Belize", "Bénin", "Bhoutan", "Bielorussie", "Birmanie", "Bolivie", "Bosnie-Herzégovine", "Botswana", "Bresil", "Brunei", "Bulgarie", "Burkina", "Burundi", "Cambodge", "Cameroun", "Canada", "Cap-Vert", "Chili", "Chine", "Chypre", "Colombie", "Comores", "Congo", "Cook", "Corée du Nord", "Corée du Sud", "Costa Rica", "Côte d'Ivoire", "Croatie", "Cuba", "Danemark", "Djibouti", "Dominique", "Egypte", "Émirats arabes unis", "Equateur", "Erythree", "Espagne", "Estonie", "Etats-Unis", "Ethiopie", "France", "Fidji", "Finlande", "Gabon", "Gambie", "Georgie", "Ghana", "Grèce", "Grenade", "Guatemala", "Guinée", "Guinée-Bissao", "Guinée équatoriale", "Guyana", "Haiti", "Honduras", "Hongrie", "Inde", "Indonesie", "Iran", "Iraq", "Irlande", "Islande", "Israël", "Italie", "Jamaique", "Japon", "Jordanie", "Kazakhstan", "Kenya", "Kirghizistan", "Kiribati", "Koweit", "Laos", "Lesotho", "Lettonie", "Liban", "Liberia", "Libye", "Liechtenstein", "Lituanie", "Luxembourg", "Macedoine", "Madagascar", "Malaisie", "Malawi", "Maldives", "Mali", "Malte", "Maroc", "Marshall", "Maurice", "Mauritanie", "Mexique", "Micronesie", "Moldavie", "Monaco", "Mongolie", "Mozambique", "Namibie", "Nauru", "Nepal", "Nicaragua", "Niger", "Nigeria", "Niue", "Norvège", "Nouvelle-Zelande", "Oman", "Ouganda", "Ouzbekistan", "Pakistan", "Panama", "Papouasie-Nouvelle Guinee", "Paraguay", "Pays-Bas", "Perou", "Philippines", "Pologne", "Portugal", "Qatar", "Republique centrafricaine", "Republique dominicaine", "Republique tcheque", "Roumanie", "Royaume-Uni", "Russie", "Rwanda", "Saint-Christophe-et-Nieves", "Sainte-Lucie", "Saint-Marin", "Saint-Siège", "Saint-Vincent-et-les Grenadines", "Salomon", "Salvador", "Samoa occidentales", "Sao Tome-et-Principe", "Senegal", "Seychelles", "Sierra Leone", "Singapour", "Slovaquie", "Slovenie", "Somalie", "Soudan", "Sri Lanka", "Suède", "Suisse", "Suriname", "Swaziland", "Syrie", "Tadjikistan", "Tanzanie", "Tchad", "Thailande", "Togo", "Tonga", "Trinite-et-Tobago", "Tunisie", "Turkmenistan", "Turquie", "Tuvalu", "Ukraine", "Uruguay", "Vanuatu", "Venezuela", "Viet Nam", "Yemen", "Yougoslavie", "Zaire", "Zambie", "Zimbabwe"];

        $options = '';
        foreach ($countries as $c) {
            $options .= '<option ' . ($selected == $c ? 'selected' : '') . ' value="' . $c . '">' . $c . '</option>';
        }
        return new Markup($options, 'UTF-8');
    }

    public function getCountriesCallCode($selected = 225, $countries_codes = null)
    {
        $countries_codes = $countries_codes ?? ["Etats Unis d'Amérique" => 1, "Canada" => 1, "Fédération russe" => 7, "Kazakhstan" => 7, "Ouzbekistan" => 7, "Egypte" => 20, "Afrique du Sud" => 27, "Grèce" => 30, "Pays-Bas" => 31, "Belgique" => 32, "France" => 33, "Espagne" => 34, "Hongrie" => 36, "Italie" => 39, "Vatican" => 39, "Roumanie" => 40, "Liechtenstein" => 41, "Suisse" => 41, "Autriche" => 43, "Royaume-Uni" => 44, "Danemark" => 45, "Suède" => 46, "Norvège" => 47, "Pologne" => 48, "Allemagne" => 49, "Pérou" => 51, "Mexique Centre" => 52, "Cuba" => 53, "Argentine" => 54, "Brésil" => 55, "Chili" => 56, "Colombie" => 57, "Vénézuela" => 58, "Malaisie" => 60, "Australie" => 61, "Ile Christmas" => 61, "Indonésie" => 62, "Philippines" => 63, "Nouvelle-Zélande" => 64, "Singapour" => 65, "Thaïlande" => 66, "Japon" => 81, "Corée du Sud" => 82, "Viêt-Nam" => 84, "Chine" => 86, "Turquie" => 90, "Inde" => 91, "Pakistan" => 92, "Afghanistan" => 93, "Sri Lanka" => 94, "Union Birmane" => 95, "Iran" => 98, "Maroc" => 212, "Algérie" => 213, "Tunisie" => 216, "Libye" => 218, "Gambie" => 220, "Sénégal" => 221, "Mauritanie" => 222, "Mali" => 223, "Guinée" => 224, "Côte d'Ivoire" => 225, "Burkina Faso" => 226, "Niger" => 227, "Togo" => 228, "Bénin" => 229, "Maurice" => 230, "Libéria" => 231, "Sierra Leone" => 232, "Ghana" => 233, "Nigeria" => 234, "République du Tchad" => 235, "République Centrafricaine" => 236, "Cameroun" => 237, "Cap-Vert" => 238, "Sao Tomé-et-Principe" => 239, "Guinée équatoriale" => 240, "Gabon" => 241, "Bahamas" => 242, "Congo" => 242, "Congo Zaïre (Rep. Dem.)" => 243, "Angola" => 244, "Guinée-Bissao" => 245, "Barbade" => 246, "Ascension" => 247, "Seychelles" => 248, "Soudan" => 249, "Rwanda" => 250, "Ethiopie" => 251, "Somalie" => 252, "Djibouti" => 253, "Kenya" => 254, "Tanzanie" => 255, "Ouganda" => 256, "Burundi" => 257, "Mozambique" => 258, "Zambie" => 260, "Madagascar" => 261, "Réunion" => 262, "Zimbabwe" => 263, "Namibie" => 264, "Malawi" => 265, "Lesotho" => 266, "Botswana" => 267, "Antigua-et-Barbuda" => 268, "Swaziland" => 268, "Mayotte" => 269, "République comorienne" => 269, "Saint Hélène" => 290, "Erythrée" => 291, "Aruba" => 297, "Ile Feroe" => 298, "Groà«nland" => 299, "Iles vierges américaines" => 340, "Iles Caïmans" => 345, "Espagne" => 349, "Gibraltar" => 350, "Portugal" => 351, "Luxembourg" => 352, "Irlande" => 353, "Islande" => 354, "Albanie" => 355, "Malte" => 356, "Chypre" => 357, "Finlande" => 358, "Bulgarie" => 359, "Lituanie" => 370, "Lettonie" => 371, "Estonie" => 372, "Moldavie" => 373, "Arménie" => 374, "Biélorussie" => 375, "Andorre" => 376, "Monaco" => 377, "Saint-Marin" => 378, "Ukraine" => 380, "Yougoslavie" => 381, "Croatie" => 385, "Slovénie" => 386, "Bosnie-Herzégovine" => 387, "Macédoine" => 389, "Italie" => 390, "République Tchèque" => 420, "Slovaquie" => 421, "Liechtenstein" => 423, "Bermudes" => 441, "Grenade" => 473, "Iles Falklands" => 500, "Belize" => 501, "Guatemala" => 502, "Salvador" => 503, "Honduras" => 504, "Nicaragua" => 505, "Costa Rica" => 506, "Panama" => 507, "Haïti" => 509, "Guadeloupe" => 590, "Bolivie" => 591, "Guyane" => 592, "Equateur" => 593, "Guinée Française" => 594, "Paraguay" => 595, "Antilles Françaises" => 596, "Suriname" => 597, "Uruguay" => 598, "Antilles hollandaise" => 599, "Saint Eustache" => 599, "Saint Martin" => 599, "Turks et caicos" => 649, "Monteserrat" => 664, "Saipan" => 670, "Guam" => 671, "Antarctique-Casey" => 672, "Antarctique-Scott" => 672, "Ile de Norfolk" => 672, "Brunei Darussalam" => 673, "Nauru" => 674, "Papouasie - Nouvelle Guinée" => 675, "Tonga" => 676, "Iles Salomon" => 677, "Vanuatu" => 678, "Fidji" => 679, "Palau" => 680, "Wallis et Futuna" => 681, "Iles Cook" => 682, "Niue" => 683, "Samoa Américaines" => 684, "Samoa occidentales" => 685, "Kiribati" => 686, "Nouvelle-Calédonie" => 687, "Tuvalu" => 688, "Polynésie Française" => 689, "Tokelau" => 690, "Micronésie" => 691, "Marshall" => 692, "Sainte-Lucie" => 758, "Dominique" => 767, "Porto Rico" => 787, "République Dominicaine" => 809, "Saint-Vincent-et-les Grenadines" => 809, "Corée du Nord" => 850, "Hong Kong" => 852, "Macao" => 853, "Cambodge" => 855, "Laos" => 856, "Trinité-et-Tobago" => 868, "Saint-Christophe-et-Niévès" => 869, "Atlantique Est" => 871, "Marisat (Atlantique Est)" => 872, "Marisat (Atlantique Ouest)" => 873, "Atlantique Ouest" => 874, "Jamaïque" => 876, "Bangladesh" => 880, "Taiwan" => 886, "Maldives" => 960, "Liban" => 961, "Jordanie" => 962, "Syrie" => 963, "Iraq" => 964, "Koweït" => 965, "Arabie saoudite" => 966, "Yémen" => 967, "Oman" => 968, "Palestine" => 970, "Emirats arabes unis" => 971, "Israà«l" => 972, "Bahreïn" => 973, "Qatar" => 974, "Bhoutan" => 975, "Mongolie" => 976, "Népal" => 977, "Tadjikistan (Rep. du)" => 992, "Turkménistan" => 993, "Azerbaïdjan" => 994, "Géorgie" => 995, "Kirghizistan" => 996, "Bahamas" => 1242, "Barbade" => 1246, "Anguilla" => 1264, "Antigua et Barbuda " => 1268, "Vierges Britanniques (Iles)" => 1284, "Vierges Américaines (Iles)" => 1340, "Cayman (Iles)" => 1345, "Bermudes" => 1441, "Grenade" => 1473, "Turks et Caïcos (Iles)" => 1649, "Montserrat" => 1664, "Sainte-Lucie" => 1758, "Dominique" => 1767, "Saint-Vincent-et-Grenadines" => 1784, "Porto Rico" => 1787, "Hawaï" => 1808, "Dominicaine (Rep.)" => 1809, "Saint-Vincent-et-Grenadines" => 1809, "Trinité-et-Tobago" => 1868, "Saint-Kitts-et-Nevis" => 1869, "Jamaïque" => 1876, "Norfolk (Ile)" => 6723];

        $options = '';
        foreach ($countries_codes as $country => $code) {
            $options .= '<option ' . ($selected == $code ? 'selected' : '') . ' value="' . $code . '">' . $country . ' : ' . $code . '</option>';
        }
        return new Markup($options, 'UTF-8');
    }

    public function fancyboxData(array $big_items, $title = null)
    {
        $asset_serv = $this->please->serve('asset');
        $final = [];
        foreach ($big_items as $items) {
            foreach ($items as $item) {
                $title = $title ?? attr($item, 'title');
                $relUrl = $asset_serv->smartImage($item);
                $final[] = [
                    "caption" => $title,
                    "relUrl" =>  $relUrl,
                    "type" => $item['human_type'],
                    "thumb" => $asset_serv->getImage($title, $relUrl, 100, 100, 'r')
                ];
            }
        }
        return json_encode($final, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function isMultidimensional(array $array): bool
    {
        foreach ($array as $item) {
            if (is_array($item)) {
                return true;
            }
        }
        return false;
    }

    public function badge($data, $separator = '|')
    {
        // Normalisation des données d'entrée
        $data = $this->normalizeBadgeData($data);

        $result = 0;
        $stringServ = $this->please->serve('string');

        if (!empty($data)) {
            if (count($data) === 1) {
                $result = $stringServ->countViews($data[0], '', '');
            } else {
                $result = implode(
                    '<i class="badge-separator mx-1">' . $separator . '</i>',
                    array_map(function ($number) use ($stringServ) {
                        $val = ($stringServ->countViews($number, '', '') ?: 0);
                        return '<em class="badge-count badge-count-' . $val . '">' . $val . '</em>';
                    }, $data)
                );
            }
        }

        return new Markup($result ?: 0, 'UTF-8');
    }

    public function bodyClassNames($showPreloader = true)
    {
        $post = $this->please->serve('post')->get();
        $classes = [];
        if ($showPreloader === false && $this->please->serve('env')->isDev()) {
            $classes[] = 'hide-app-preloader-wrapper';
        }
        $classes[] = 'layout-' . ($post['layout'] ?? $post['coll_name'] ?? 'default');
        $classes[] = 'type-' . attr($post, 'type');
        $classes[] = 'loaded';
        $classes[] = 'authed-' . ($this->please->serve('security')->userIsAuthenticated() ? 'true' : 'false');
        $classes[] = 'theme-' . $this->please->serve('mix')->getCurrentTheme();
        $classes[] = $post['body_class'] ?? '';

        return implode(' ', $classes);
    }

    public function preloader($v = 1, $icon = null, $maxWidth = '100%', $className = null)
    {
        $assetServ = $this->please->serve('asset');
        $content = $this->please->serve('cache')->do(function () use ($v, $icon, $maxWidth, $className, $assetServ) {
            $hiddenClass = $this->please->isXHR() ? 'hidden' : '';
            $icon = '<img style="height:40px;margin:55px auto 0" src="' . ($icon ? $assetServ->getAsset($icon) : $assetServ->getCDN('swagg/assets/img/loading-spinner.gif')) . '?v=' . uniqid() . '">';
            return '<div class="app-preloader-wrapper ' . $className . $hiddenClass . '">
            <div style="text-align:center">
                <div class="logo-wrapper"><img style="margin:auto;width:' . $maxWidth . ';border-radius:4px" class="logo" src="' . $assetServ->getLogoSrc($v) . '"></div>
                ' . $icon . '
            </div>
        </div>';
        }, [$v, $icon, $maxWidth, $className]);

        return new Markup($content, 'UTF-8');
    }

    public function getCurrentTheme()
    {
        $themeServ = attr($this->please->serve('security')->getCurrentUser(), 'preferred_theme');
        return $themeServ;
    }

    public function isSearchEngineBot(): bool
    {
        if ($this->please->getInput('_robot')) {
            return true;
        }
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);

        // Liste étendue de bots connus
        $bots = [
            // --- Moteurs de recherche ---
            'googlebot',
            'googlebot-image',
            'googlebot-news',
            'googlebot-video',
            'bingbot',
            'msnbot',
            'adidxbot',
            'bingpreview',
            'slurp', // Yahoo
            'duckduckbot',
            'baiduspider',
            'baiduspider-image',
            'baiduspider-video',
            'yandexbot',
            'yandeximages',
            'yandexvideo',
            'yandexmobilebot',
            'yandexmedia',
            'sogou spider',
            'sogou web spider',
            'sogou inst spider',
            'exabot',
            'facebot',
            'facebookexternalhit',
            'facebookcatalog',
            'ia_archiver', // Alexa / Wayback Machine
            'applebot',
            'petalbot', // Huawei
            'seznambot',
            'qwantify',
            'mj12bot',
            'ahrefsbot',
            'semrushbot',
            'dotbot',
            'rogerbot',
            'screaming frog',
            'coccocbot',
            'openai',
            'chatgpt-user',
            'gptbot',
            'yeti', // Naver
            'curl',
            'wget',
            'python-requests', // scripts automatisés
            'archive.org_bot',
            'oai', // OpenAI crawlers
            'buzzsumo',
            'linkdexbot',
            'pinterestbot',
            'tumblr',
            'twitterbot',
            'linkedinbot',
            'embedly',
            'redditbot',
            'slackbot',
            'whatsapp',
            'telegrambot',
            'discordbot',
            'snapchatbot',
            'okhttp', // Android client (souvent utilisé par crawlers)

            // --- Autres crawlers & outils SEO ---
            'uptimerobot',
            'pingdom',
            'monitis',
            'site24x7',
            'gtmetrix',
            'pagespeed', // Google PageSpeed Insights
            'crawler',
            'spider',
            'robot',
            'bot', // fallback générique
        ];

        foreach ($bots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    public function isNotSearchEngineBot(): bool
    {
        return !$this->isSearchEngineBot();
    }

    public function extractBDFields()
    {
        $start_time = microtime(true);
        try {
            $pdo = $this->please->getPDO();

            $excludedColumns = [
                'roles',
                'role',
                'password',
                'old_password',
                'username',
                'username_slugged',
                'forgot_token',
                'mle',
                'lastname',
                'firstname',
                'telephone',
                'email',
                'activated',
                'user_id',
                'created_at',
                'updated_at',
                'title',
                'parent_id',
                'second_title',
                'message',
                'post_id',
                'href',
                'doc_id',
                'date',
                'val',
                'meta',
                'job',
                'status',
                'url',
                'val',
                'disk_folder',
                'priority',
                'description',
                'custom_href',
                'slug',
                'keywords',
                'name',
                'link_type',
                'acf_type',
                'layout',
                'layout_single',
                'in_menu',
                'published',
                'allow_comments',
                'extra_data',
                'id',
                'uid',
                'back_layout',
                'icon',
                'fields_list',
                'create_via_drawer',
                'only_title',
                'single_doc',
                'coll_name',
                'parent',
                'front_layout',
                'name_alias',
                'rank',
                'acf_parent_id',
                'acf',
                'image_id',
                'input_name',
                'ids',
                'human_type',
                'folder_id',
                'human_type',
                'params',
                'type',
                'action',
                'controller',
                'route_params',
                'document_id',
                'document',
                'line',
                'data',
                'post',
                'file',
                'previous',
                'path',
                'enabled',
                'granted',
                'authenticated_at',
                'preferred_theme',
                'reservedProps',
                'textOverlay',
                'ident',
                'stay_connected',
                'theme'
            ];

            $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
            $excludedLower = array_map('strtolower', $excludedColumns);
            $excludedColumnsCount = count($excludedColumns);

            $query = "
                SELECT TABLE_NAME as table_name, COLUMN_NAME as column_name
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = :database
                ORDER BY TABLE_NAME, ORDINAL_POSITION
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([':database' => $database]);
            $results = $stmt->fetchAll();

            $tableColumns = [];
            foreach ($results as $row) {
                $tableName = $row['table_name'];
                $columnName = $row['column_name'];

                if (in_array(strtolower($columnName), $excludedLower)) {
                    continue;
                }

                $tableColumns[$tableName][] = $columnName;
            }

            // Calculer le nombre total de colonnes
            $totalColumns = $this->getTotalColumnsCountFromArray($tableColumns);
            $tablesCount = count($tableColumns);

            // Fonction pour formater var_export sans index numériques
            $formatVarExport = function ($variable) {
                $export = var_export($variable, true);
                // Supprime les index numériques des tableaux simples
                $export = preg_replace('/\s*(\d+)\s*=>\s*/', '', $export);
                // Nettoie l'indentation
                $export = preg_replace('/array\s*\(\s*\n\s*/', 'array(', $export);
                $export = preg_replace('/\n\s*\)/', ')', $export);
                $export = preg_replace('/,\s*\n\s*\)/', ')', $export);
                // Réduit les espaces multiples
                $export = preg_replace('/\s+/', ' ', $export);
                return $export;
            };

            // Construire le contenu de la classe
            $phpContent = '<?php
namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class ValidPropsService extends AbstractController
{
    public $please;
    
    private static $tableColumns = ' . $formatVarExport($tableColumns) . ';
    
    private static $excludedColumns = ' . $formatVarExport($excludedColumns) . ';
    
    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }
    
    /**
     * Retourne les colonnes d\'une table spécifique
     * 
     * @param string $tableName Nom de la table
     * @return array Les colonnes de la table ou tableau vide si la table n\'existe pas
     */
    public function getColumnsForTable(string $tableName): array
    {
        return self::$tableColumns[$tableName] ?? [];
    }
    
    /**
     * Liste toutes les tables disponibles
     * 
     * @return array Liste des tables
     */
    public function getAllTables(): array
    {
        return array_keys(self::$tableColumns);
    }
    
    /**
     * Vérifie si une table existe
     * 
     * @param string $tableName Nom de la table
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        return isset(self::$tableColumns[$tableName]);
    }
    
    /**
     * Vérifie si une colonne existe dans une table
     * 
     * @param string $tableName Nom de la table
     * @param string $columnName Nom de la colonne
     * @return bool
     */
    public function columnExists(string $tableName, string $columnName): bool
    {
        if (!isset(self::$tableColumns[$tableName])) {
            return false;
        }
        
        return in_array($columnName, self::$tableColumns[$tableName]);
    }
    
    /**
     * Récupère toutes les colonnes de toutes les tables
     * 
     * @return array Structure complète [table => [colonne1, colonne2, ...]]
     */
    public function getAllColumns(): array
    {
        return self::$tableColumns;
    }
    
    /**
     * Récupère le nombre de tables
     * 
     * @return int
     */
    public function getTablesCount(): int
    {
        return count(self::$tableColumns);
    }
    
    /**
     * Récupère le nombre total de colonnes
     * 
     * @return int
     */
    public function getTotalColumnsCount(): int
    {
        $total = 0;
        foreach (self::$tableColumns as $columns) {
            $total += count($columns);
        }
        return $total;
    }
    
    /**
     * Récupère les tables qui contiennent une colonne spécifique
     * 
     * @param string $columnName Nom de la colonne à rechercher
     * @return array Liste des tables contenant cette colonne
     */
    public function getTablesContainingColumn(string $columnName): array
    {
        $tables = [];
        foreach (self::$tableColumns as $table => $columns) {
            if (in_array($columnName, $columns)) {
                $tables[] = $table;
            }
        }
        return $tables;
    }

    /**
     * Récupère la liste des champs exclus de la base de données
     * 
     * @return array Liste des colonnes exclues
     */
    public function getExcludedColumns(): array
    {
        return self::$excludedColumns;
    }
    
    /**
     * Vérifie si une colonne est exclue
     * 
     * @param string $columnName Nom de la colonne à vérifier
     * @return bool
     */
    public function isColumnExcluded(string $columnName): bool
    {
        return in_array(strtolower($columnName), array_map(\'strtolower\', self::$excludedColumns));
    }
    
    /**
     * Récupère les métadonnées de génération
     * 
     * @return array
     */
    public function getMetadata(): array
    {
        return [
            \'database\' => \'' . addslashes($database) . '\',
            \'generated_at\' => \'' . date('Y-m-d H:i:s') . '\',
            \'tables_count\' => ' . $tablesCount . ',
            \'columns_count\' => ' . $totalColumns . ',
            \'excluded_columns_count\' => ' . $excludedColumnsCount . ',
        ];
    }
}

// Documentation
// Base de données : ' . $database . '
// Généré le : ' . date('Y-m-d H:i:s') . '
// Tables traitées : ' . $tablesCount . '
// Colonnes totales : ' . $totalColumns . '
// Colonnes exclues : ' . $excludedColumnsCount . '
?>';

            // Chemin où sauvegarder le fichier
            $bundlePath = dirname(__FILE__);
            $outputFile = $bundlePath . DIRECTORY_SEPARATOR . 'ValidPropsService.php';

            $phpContent = str_replace("array('", "['", $phpContent);
            $phpContent = str_replace("array (", "[", $phpContent);
            $phpContent = str_replace(",)", "]", $phpContent);


            // Sauvegarder le fichier
            if (file_put_contents($outputFile, $phpContent)) {
                $content = "✅ Fichier ValidPropsService.php généré avec succès !<br>";
                $content .= "📁 Chemin : " . $outputFile . "<br>";
                $content .= "🗃️ Base de données : " . $database . "<br>";
                $content .= "📊 Tables trouvées : " . $tablesCount . "<br>";
                $content .= "🔤 Colonnes totales : " . $totalColumns . "<br>";
                $content .= "🚫 Colonnes exclues : " . $excludedColumnsCount . "<br>";

                // Afficher un aperçu des tables
                $content .= "<br><strong>Aperçu des tables :</strong><br>";
                $content .= "<div style='max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;'>";
                foreach ($tableColumns as $table => $columns) {
                    $columnCount = count($columns);
                    $content .= "<div style='margin-bottom: 5px;'>";
                    $content .= "<strong>{$table}</strong> ({$columnCount} colonnes)";
                    if ($columnCount > 0) {
                        $content .= "<div style='margin-left: 20px; font-size: 12px; color: #666;'>";
                        $content .= implode(', ', array_slice($columns, 0, 5));
                        if ($columnCount > 5) {
                            $content .= ", ... (+" . ($columnCount - 5) . " autres)";
                        }
                        $content .= "</div>";
                    }
                    $content .= "</div>";
                }
                $content .= "</div>";
            } else {
                $content = "❌ Erreur lors de l'écriture du fichier.";
            }
        } catch (\PDOException $e) {
            $content = "❌ Erreur de connexion à la base de données : " . $e->getMessage();
        } catch (\Exception $e) {
            $content = "❌ Erreur : " . $e->getMessage();
        }

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        $responseContent = $content . "<br><br>⏱️ Temps d'exécution : {$execution_time} secondes";

        return new Response($responseContent);
    }

    public function autoDecodeBase64Array($array, $specificKeys = [])
    {
        foreach ($array as $key => $value) {
            // Si on a spécifié des clés, ne traiter que celles-là
            if (!empty($specificKeys) && !in_array($key, $specificKeys)) {
                continue;
            }

            if (is_string($value) && !empty($value)) {
                $decoded = base64_decode($value, true);
                if ($decoded !== false && !empty($decoded) && $decoded !== $value) {
                    $array[$key] = $decoded;
                }
            }
        }
        return $array;
    }

    public function decodeIfBase64Encoded($string)
    {
        if (empty($string)) {
            return $string;
        }

        // Essayer base64 d'abord
        $base64Decoded = base64_decode($string, true);
        if ($base64Decoded !== false && !empty($base64Decoded)) {
            return $base64Decoded;
        }

        // Essayer URL decode ensuite
        if (strpos($string, '%') !== false) {
            $urlDecoded = urldecode($string);
            if ($urlDecoded !== $string) {
                return $urlDecoded;
            }
        }

        return $string;
    }

    /**
     * Méthode utilitaire pour compter les colonnes totales
     */
    private function getTotalColumnsCountFromArray(array $tableColumns): int
    {
        $total = 0;
        foreach ($tableColumns as $columns) {
            $total += count($columns);
        }
        return $total;
    }

    /**
     * Normalise les données du badge
     * 
     * @param mixed $data Les données à normaliser
     * @return array Un tableau de nombres
     */
    private function normalizeBadgeData($data): array
    {
        // Si c'est déjà un tableau
        if (is_array($data)) {
            // Si le tableau est vide, retourner [0]
            if (empty($data)) {
                return [0];
            }

            // Si le tableau contient un tableau (cas d'erreur)
            if (isset($data[0]) && is_array($data[0])) {
                return [count($data[0])];
            }

            // Filtrer les valeurs null, false, etc.
            $filtered = array_filter($data, function ($value) {
                return $value !== null && $value !== false && $value !== '';
            });

            if (empty($filtered)) {
                return [0];
            }

            return $filtered;
        }

        // Si c'est un nombre
        if (is_numeric($data)) {
            return [(int) $data];
        }

        // Si c'est un objet Countable
        if ($data instanceof \Countable) {
            return [count($data)];
        }

        // Si c'est un objet avec une méthode count()
        if (is_object($data) && method_exists($data, 'count')) {
            return [$data->count()];
        }

        // Si c'est une chaîne de caractères
        if (is_string($data)) {
            // Essayer de la convertir en nombre
            if (is_numeric($data)) {
                return [(int) $data];
            }
            // Sinon, compter la longueur de la chaîne (ou autre logique)
            return [strlen($data)];
        }

        // Par défaut, retourner 0
        return [0];
    }
}
