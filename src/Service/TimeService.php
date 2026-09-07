<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TimeService extends AbstractController
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function getMonth($dateTime = null, $type = null, $months_prefixed = null, $ellipsis = null)
    {
        $dateTime = !is_null($dateTime) ? (is_string($dateTime) ? new \DateTime($dateTime) : $dateTime) : new \DateTime();
        $monthsWithout = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
        $monthsWith = array('de janvier', 'de février', 'de mars', 'd\'avril', 'de mai', 'de juin', 'de juillet', 'd\'août', 'de septembre', 'd\'octobre', 'de novembre', 'de décembre');
        /*    $monthsWith[3] = 'd\'avril';
        $monthsWith[7] = 'd\'août';
        $monthsWith[9] = 'd\'octobre';*/
        $month = (is_null($months_prefixed)) ? $monthsWithout[intval($dateTime->format("m")) - 1] : $monthsWith[intval($dateTime->format("m")) - 1];

        if (!is_null($type) && $type == 'number') {
            $month = $dateTime->format("m");
        }

        if (is_numeric($ellipsis)) {
            if ($month == 'août') {
                $ellipsis = $ellipsis + 1;
            }
            $month = substr($month, 0, $ellipsis) . '.';
        }

        return $month;
    }

    public function getFrenchDateFormatToUsFormat($date, $delimiter = '-')
    {
        $exploded = explode($delimiter, $date);
        $formatted = $exploded[2] . '-' . $exploded[1] . '-' . $exploded[0];
        return new \DateTime($formatted);
    }

    public function getFrenchDate($dateTime = null, $format = "D/d/M/Y")
    {
        if (is_string($dateTime)) {
            $dateTime = new \DateTime($dateTime, new \DateTimeZone('UTC'));
        } else {
            $dateTime = is_null($dateTime) ? new \DateTime() : $dateTime;
        }
        return $this->transDateToFrench($dateTime->format($format));
    }

    public function getHumanDiff($timestamp, $tokens = null)
    {
        if (!is_string($timestamp)) {
            $timestamp = $timestamp->format('Y-m-d H:i:s');
        }
        $timestamp = time() - strtotime($timestamp); // to get the time since that moment
        $timestamp = ($timestamp < 1) ? 1 : $timestamp;
        $tokens = array(
            31536000 => 'an', //year
            2592000 => 'mois', //month
            604800 => 'semaine', //week
            86400 => 'jour', //day
            3600 => 'heure', //hour
            60 => 'minute', //minute
            1 => 'seconde', //second
        );

        foreach ($tokens as $unit => $text) {
            if ($timestamp < $unit) {
                continue;
            }

            $numberOfUnits = floor($timestamp / $unit);
            return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? $text !== 'mois' ? 's' : '' : '');
        }
        return '';
    }

    public function getTTL(string $humanTime = "1 day", string $maxJitter = "0 second"): int
    {
        $seconds = $this->humanTime($humanTime);
        $jitterMax = max(0, $this->humanTime($maxJitter));
        $lastCacheKey = $this->please->getStorage('last-cache-key');

        if ($jitterMax > 0 && $lastCacheKey !== '') {
            $hash = crc32($lastCacheKey); // 32-bit int
            $jitter = $hash % ($jitterMax + 1); // décalage déterministe
        } else {
            $jitter = 0;
        }

        return $seconds + $jitter;
    }

    public function getTimeAgo($timestamp, $tokens = null)
    {
        return $this->getHumanDiff($timestamp, $tokens);
    }

    public function getTimeRemaining($future_date, $format = "%D jours, %H heures, %I minutes, %S secondes")
    {
        $now = new \DateTime();
        if (is_string($future_date)) {
            $future_date = new \DateTime($future_date);
        }
        $interval = $future_date->diff($now);
        return (object) ["interval" => $interval, "format" => $interval->format($format)];
    }

    public function getDateTime($datetime = null)
    {
        return new \DateTime(is_null($datetime) ? date("Y-m-d H:i:s") : $datetime, new \DateTimeZone('GMT'));
    }

    public function getDaysOptions($label = 'Jour', $selected = null, $required = true)
    {
        $options = '<option value="" style="font-weight:bold" selected ' . ($required ? 'disabled' : '') . '>' . $label . '</option>';
        for ($i = 1; $i < 32; $i++) {
            $ii = strlen($i) == 1 ? '0' . $i : $i;
            $options .= '<option value="' . $i . '" ' . ($selected == $i ? 'selected' : '') . '>' . $ii . '</option>';
        }
        return $options;
    }

    public function getMonthsOptions($label = 'Mois', $selected = null, $required = true, $short = false)
    {
        $options = $short
            ? '<option value="" style="font-weight:bold" selected ' . ($required ? 'disabled' : '') . '>' . $label . '</option>
			<option value="01" ' . ($selected == 1 ? 'selected' : '') . '>jan</option>
			<option value="02" ' . ($selected == 2 ? 'selected' : '') . '>fév</option>
			<option value="03" ' . ($selected == 3 ? 'selected' : '') . '>mar</option>
			<option value="04" ' . ($selected == 4 ? 'selected' : '') . '>avr</option>
			<option value="05" ' . ($selected == 5 ? 'selected' : '') . '>mai</option>
			<option value="06" ' . ($selected == 6 ? 'selected' : '') . '>jui</option>
			<option value="07" ' . ($selected == 7 ? 'selected' : '') . '>Jui</option>
			<option value="08" ' . ($selected == 8 ? 'selected' : '') . '>aoû</option>
			<option value="09" ' . ($selected == 9 ? 'selected' : '') . '>sep</option>
			<option value="10" ' . ($selected == 10 ? 'selected' : '') . '>oct</option>
			<option value="11" ' . ($selected == 11 ? 'selected' : '') . '>nov</option>
			<option value="12" ' . ($selected == 12 ? 'selected' : '') . '>déc</option>'
            : '<option value="" style="font-weight:bold" selected ' . ($required ? 'disabled' : '') . '>' . $label . '</option>
			<option value="01" ' . ($selected == 1 ? 'selected' : '') . '>Janvier</option>
			<option value="02" ' . ($selected == 2 ? 'selected' : '') . '>Février</option>
			<option value="03" ' . ($selected == 3 ? 'selected' : '') . '>Mars</option>
			<option value="04" ' . ($selected == 4 ? 'selected' : '') . '>Avril</option>
			<option value="05" ' . ($selected == 5 ? 'selected' : '') . '>Mai</option>
			<option value="06" ' . ($selected == 6 ? 'selected' : '') . '>Juin</option>
			<option value="07" ' . ($selected == 7 ? 'selected' : '') . '>Juillet</option>
			<option value="08" ' . ($selected == 8 ? 'selected' : '') . '>Août</option>
			<option value="09" ' . ($selected == 9 ? 'selected' : '') . '>Septembre</option>
			<option value="10" ' . ($selected == 10 ? 'selected' : '') . '>Octobre</option>
			<option value="11" ' . ($selected == 11 ? 'selected' : '') . '>Novembre</option>
			<option value="12" ' . ($selected == 12 ? 'selected' : '') . '>Décembre</option>';
        return $options;
    }

    public function getYearsOptions($label = 'Années', $selected = null, $required = true, $from = null, $to = 1950, $order = 'desc')
    {
        $years = $to ?? (new \DateTime())->format('Y');
        $from = $from ?? (new \DateTime())->format('Y');
        $options = '<option value="" style="font-weight:bold" selected ' . ($required ? 'disabled' : '') . '>' . $label . '</option>';
        $order = strtoupper($order);
        if ($order == 'ASC') {
            for ($i = $from; $i < $years + 1; $i++) {
                $options .= '<option value="' . $i . '" ' . ($selected == $i ? 'selected' : '') . '>' . $i . '</option>';
            }
        } else {
            for ($i = $from; $i >= $years; $i--) {
                $options .= '<option value="' . $i . '" ' . ($selected == $i ? 'selected' : '') . '>' . $i . '</option>';
            }
        }
        return $options;
    }

    public function convertToHoursMins($time, $format = '%02dh%02dmin')
    {
        //echo convertToHoursMins(250, '%02d hours %02d minutes'); // should output 4 hours 17 minutes

        if ($time < 1) {
            return;
        }
        $hours = floor($time / 60);
        $minutes = ($time % 60);

        return $hours == 0 ? sprintf('%02dmin', $minutes) : sprintf($format, $hours, $minutes);
    }

    public function getYearsOld($birthdate)
    {
        if (is_null($birthdate)) {
            return '?';
        }
        if (!is_string($birthdate)) {
            $birthdate = $birthdate->format('Y-m-d');
        }
        //$dateOfBirth = "17-10-1985";
        $today = date("Y-m-d");
        $diff = date_diff(date_create($birthdate), date_create($today));
        return $diff->format('%y');
    }

    public function ellipsisDate($dateTime, $format = "D d M Y")
    {
        if (is_string($dateTime)) {
            $dateTime = new \DateTime($dateTime, new \DateTimeZone('UTC'));
        }
        $datetime = $this->getFrenchDate(is_null($dateTime) ? date("Y-m-d") : $dateTime, "D/d/M/Y");

        $x = explode(' ', $datetime);
        //
        $M = substr($x[2], 0, 4);
        $formatArr = [
            'D' => substr($x[0], 0, 3) . '.',
            'd' => $x[1],
            'M' => (function () use ($x, $M) {
                switch ($x[2]) {
                    case 'juin':
                        return 'juin';
                    case 'juillet':
                        return 'juil.';
                    default:
                        switch ($M) {
                            case 'fév':
                                return 'fév.';
                            case 'mai':
                                return 'mai';
                            case 'aôu':
                                return 'aoû.';
                            case 'déc':
                                return 'déc.';
                            default:
                                return substr($M, 0, 3) . '.';
                        }
                }
            })(),
            'Y' => $x[3]
        ];
        //
        $output = '';
        $xf = explode(' ', $format);
        foreach ($xf as $f) {
            if (isset($formatArr[$f])) {
                $output .= "$formatArr[$f] ";
            }
        }
        return trim($output);
    }

    private function transDateToFrench($date)
    {
        $xploded = explode('/', $date);
        $arr = [
            'Mon' => 'lundi',
            'Tue' => 'mardi',
            'Wed' => 'mercredi',
            'Thu' => 'jeudi',
            'Fri' => 'vendredi',
            'Sat' => 'samedi',
            'Sun' => 'dimanche',

            'Jan' => 'janvier',
            'Feb' => 'février',
            'Mar' => 'mars',
            'Apr' => 'avril',
            'May' => 'mai',
            'Jun' => 'juin',
            'Jul' => 'juillet',
            'Aug' => 'aôut',
            'Sep' => 'septembre',
            'Oct' => 'octobre',
            'Nov' => 'novembre',
            'Dec' => 'décembre',
        ];

        $date = '';
        foreach ($xploded as $partial) {
            if (isset($arr[$partial])) {
                $date .= $arr[$partial] . ' ';
            } else {
                $date .= $partial . ' ';
            }
        }
        return trim($date);
    }

    public function millisecAsDuration($milliseconds)
    {
        $duration = date("H:i:s", $milliseconds / 1000);
        $duration = str_replace('00:00:', '', $duration);
        $duration = str_replace('00:', '', $duration);
        $duration = strlen($duration) == 2 ? "00:$duration" : $duration;
        return $duration;
    }

    public function humanTime($time)
    {
        return strtotime("+$time") - time();

        $units = [
            'second' => 1 / 60,
            'seconds' => 1 / 60,
            'minute' => 1,
            'minutes' => 1,
            'hour' => 60,
            'hours' => 60,
            'day' => 1440,
            'days' => 1440,
            'week' => 10080,
            'weeks' => 10080,
            'month' => 43200, // Approximation pour 30 jours
            'year' => 525600,  // Année standard (365 jours)
            'years' => 525600
        ];

        preg_match('/(\d+)\s*(\w+)/', strtolower($time), $matches);

        if (!$matches) {
            return null; // Format invalide
        }

        $value = (int) $matches[1];
        $unit = $matches[2];

        return isset($units[$unit]) ? $value * $units[$unit] : null;
    }

    public function getLatestModificationDate(string $dir = 'theme')
    {
        $dir = $this->please->serve('dir')->dirPath($dir);

        if (!is_dir($dir)) {
            return null;
        }

        $latestTime = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $modTime = $file->getMTime();
                if ($modTime > $latestTime) {
                    $latestTime = $modTime;
                }
            }
        }

        return $latestTime > 0 ? date('Y-m-d H:i:s', $latestTime) : null;
    }

    public function getMaxUpdated($coll_names = null, $latest_modification_date = true)
    {
        if (is_string($coll_names)) {
            $coll_names = [$coll_names];
        }

        $queries = [];
        foreach ($coll_names as $coll) {
            $queries[] = "SELECT MAX(updated_at) as max_updated FROM $coll";
        }
        $unionQuery = implode(" UNION ", $queries);
        $result = qb()->rawQuery($unionQuery);
        $last_updated_at = max(array_column($result, 'max_updated'));

        return $last_updated_at . ($latest_modification_date ? (
            $this->getLatestModificationDate() .
            $this->getLatestModificationDate('src')
        ) : '');
    }
}
