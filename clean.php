<?php
/**
 * Base class for all singletons.
 *
 * @see http://stackoverflow.com/questions/3972628/creating-a-singleton-base-class-in-php-5-3
 */
abstract class Singleton {
    protected static $instances;

    protected function __construct() { }

    public static function instance() {
        $class = get_called_class();

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class;
        }
        return self::$instances[$class];
    }
}


/**
 * An input reader.
 */
interface Reader {
    /**
     * Can this reader read the given input source spec?
     *
     * @static
     * @abstract
     * @param string $inputSource the input source spec (e.g. filename, stream URL)
     * @return Boolean true if so; false otherwise.
     */
    public static function canRead($inputSource);

    /**
     * Open the given input source and prepare to read it.
     * @abstract
     * @param string $inputSource the input source spec (e.g. filename, stream URL)
     */
    public function open($inputSource);

    /**
     * Read the next line of input.
     *
     * @abstract
     * @return array (latitude, longitude, timestamp). If nothing left to read then returns NULL.
     */
    public function read();

    /**
     * Stop reading and close the input source.
     * @abstract
     */
    public function close();
}


/**
 * An output writer.
 */
abstract class Writer {
    /**
     * Can this writer write the given format?
     * @abstract
     * @param string $format outpt format.
     * @return boolean true if so; false otherwise.
     */
    public abstract function canWrite($format);

    /**
     * Start writing.
     */
    public function start() {}

    /**
     * Write a single data point to output.
     *
     * @abstract
     * @param array $dataPoint
     */
    public abstract function write(array $dataPoint);
    public function finish() {}
}


/**
 * Reader for CSV files.
 */
class CsvReader implements Reader {
    private $input = NULL;

    public static function canRead($inputSource)
    {
        $parts = pathinfo($inputSource);
        return 'csv' === (strtolower($parts['extension']));
    }

    public function open($inputSource)
    {
        $this->input = fopen($inputSource, 'r');
    }

    public function read()
    {
        $line = fgets($this->input);
        return !empty($line) ? str_getcsv($line) : NULL;
    }

    public function close()
    {
        fclose($this->input);
    }
}


/**
 * Writer for CSV format.
 */
class CsvWriter extends Writer {
    public function canWrite($format)
    {
        return 'csv' === strtolower($format);
    }

    public function write(array $dataPoint)
    {
        print join(',', $dataPoint) . "\n";
    }
}


/**
 * Factory for all Reader types.
 */
class ReaderFactory extends Singleton {
    private $implementations = array();

    /**
     * Register a Reader implementation.
     *
     * @param string $implementationClassName class name of implementation.
     */
    public function register($implementationClassName) {
        $this->implementations[] = $implementationClassName;
    }


    /**
     * Get Reader instance for given input source
     *
     * @param string $inputSource an input source spec.
     * @return object Reader instance.
     * @throws Exception if input source type not supported.
     */
    public function get($inputSource) {
        foreach($this->implementations as $className) {
            if (forward_static_call(array($className, 'canRead'), $inputSource)) {
                $reflector = new ReflectionClass($className);
                $reader = $reflector->newInstanceArgs();
                $reader->open($inputSource);
                return $reader;
            }
        }

        throw new Exception('Unable to handle input source: ' . $inputSource);
    }
}


/**
 * Factory for all Writer types.
 */
class WriterFactory extends Singleton {
    private $implementations = array();

    /**
     * Register a Writer implementation.
     *
     * @param string $implementationClassName class name of implementation.
     */
    public function register($implementationClassName) {
        $this->implementations[] =  $implementationClassName;
    }

    /**
     * Get Writer instance for given outpu format.
     *
     * @param string $format output format.
     * @return object Writer instance.
     * @throws Exception if format not supported.
     */
    public function get($format) {
        foreach($this->implementations as $className) {
            if (forward_static_call(array($className, 'canWrite'), $format)) {
                $reflector = new ReflectionClass($className);
                $writer = $reflector->newInstanceArgs();
                return $writer;
            }
        }

        throw new Exception('Unsupported output format: ' . $format);
    }
}


/**
 * Route cleaner.
 */
class RouteCleaner extends Singleton {
    const TIGHT_ANGLE = 10;
    const TIGHT_ANGLE_SPEED_LIMIT_KMPH = 50;

    private $inputSource = null;
    private $reader = null;
    private $writer = null;

    private $errorPoints = array();

    /**
     * Main entry point.
     * @param array $argv
     * @throws Exception
     */
    public function main(array $argv) {
        try {
            // get input source
            $this->inputSource = NULL;
            foreach ($argv as $arg) {
                if (basename(__FILE__) !== $arg && 0 !== substr_compare($arg, '--', 0, 2)) {
                    $this->inputSource = $arg;
                    break;
                }
            }

            // check options
            $options = array_merge(array(
                'output-format' => 'csv'
            ), getopt(null, array(
                'help',
                'output-format::'
            )));

            // user wants help?
            if (isset($options['help'])) {
                $this->printHelp();
                exit();
            }

            if (empty($this->inputSource)) {
                throw new Exception('No input source specified!');
            }

            $this->reader = ReaderFactory::instance()->get($this->inputSource);
            $this->writer = WriterFactory::instance()->get($options['output-format']);

            $this->clean();

        } catch (Exception $e) {
            print $e->getMessage() . "\n";
            $this->printHelp();
            exit(-1);
        }
    }


    /**
     * Clean the route.
     */
    private function clean() {
        $points = array();

        $this->writer->start();

        while ( NULL !== ($current = $this->reader->read())) {
            $points[] = $current;

            // once we have 3 points
            if (3 === count($points)) {
                $points = $this->filterBadPoints($points);

                if (3 === count($points)) {
                    // remove oldest point and output it
                    $this->writer->write(array_shift($points));
                }
            }
        }

        // any remaining points? (it's hard to know if the final point is erroneous or not since there's no angle
        // we can calculate so we simply trust it.
        array_walk($points, array($this->writer, 'write'));


        $this->reader->close();
        $this->writer->finish();
    }


    /**
     * Filter bad route points from given list of points.
     * @param array $points list of 3 points. Each point is an array of form (lat, long, timestamp).
     * @return array filtered list of points.
     */
    private function filterBadPoints($points) {
        list($lat1, $lng1, $ts1) = $points[0];
        list($lat2, $lng2, $ts2) = $points[1];
        list($lat3, $lng3, $ts3) = $points[2];

        // get distances between points
        $distKm1 = $this->distanceBetweenTwoGeoPoints($lat1, $lng1, $lat2, $lng2);
        $distKm2 = $this->distanceBetweenTwoGeoPoints($lat2, $lng2, $lat3, $lng3);
        $distKm3 = $this->distanceBetweenTwoGeoPoints($lat1, $lng1, $lat3, $lng3);

        // if points 1 and 3 are the same but point 2 is different...
        if (0 == $distKm3 && 0 != $distKm1) {
            // ...then it's highly likely that point 2 is erroneous
            $this->errorPoints[] = $points[1];
            $points = array($points[0], $points[2]);
        }
        // as long as all all distances are non-zero
        else if (0 != $distKm1 && 0 != $distKm2 && 0 != $distKm3) {
            // get speed
            $speedKmh = ($distKm1 + $distKm2) / ($ts3 - $ts1) * 3600;

            // get angles
            $angles = $this->solveThreeSidedTriangle($distKm1, $distKm2, $distKm3);

            /*
             * Criteria for error:
             *  - If above speed limit and the angle is tight.
             */
            if (
                (self::TIGHT_ANGLE > $angles[1] && self::TIGHT_ANGLE_SPEED_LIMIT_KMPH < $speedKmh)
            ) {
                $this->errorPoints[] = $points[1];
                $points = array($points[0], $points[2]);
            }
        }

        return $points;
    }


    /**
     * Get distance between two lat-long points.
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float distance.
     */
    private function distanceBetweenTwoGeoPoints($lat1, $lng1, $lat2, $lng2) {
        // Haversine formula
        $deg2rad = M_PI / 180;
        $r = 6372.797; // mean radius of Earth in km
        $dlat = ($lat2 - $lat1) * $deg2rad;
        $dlng = ($lng2 - $lng1) * $deg2rad;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $r * $c;
    }


    /**
     * Print command-line usage help.
     */
    private function printHelp() {
        print "\n";
        print "Usage:\n\tphp clean.php [..options..] <input_file>";
        print "\n\nOptions:\n\t--output-format=<format>\t - the output format, only 'csv' supported at the moment.";
        print "\n\t--help\t\t\t\t - this help message.";
        print "\n\n";
    }



    /**
     * Calculate the angles of given 3-sided triangle.
     *
     *
     *                  C
     *                 / \
     *                / y \
     *             b /     \ a
     *              /       \
     *             / x     z \
     *            A --------- B
     *                  c
     *
     *
     * @param float $b distance from A to C
     * @param float $a distance from C to B
     * @param float $c distance from A to B
     *
     * @return array angles (x,y,z) in degrees.
     */
    private function solveThreeSidedTriangle($b, $a, $c) {
        $rad2deg = 180.0 / M_PI;

        $x = acos(($b*$b + $c*$c - $a*$a) / (2.0 * $b * $c)) * $rad2deg;
        $z = acos(($a*$a + $c*$c - $b*$b) / (2.0 * $a * $c)) * $rad2deg;
        $y = 180 - $x - $z;

        return array($x, $y, $z);
    }

//    private function outputPointCalculations($p1, $p2, $p3) {
//        list($lat1, $lng1, $ts1) = $p1;
//        list($lat2, $lng2, $ts2) = $p2;
//        list($lat3, $lng3, $ts3) = $p3;
//
//        print "\n------------------------------------------------------------";
//        print "\n[$lat1, $lng1] ==> [$lat2, $lng2] ==> [$lat3, $lng3]:";
//        print "\n------------------------------------------------------------";
//
//        $dist1 = $this->distanceBetweenTwoGeoPoints($lat1, $lng1, $lat2, $lng2);
//        $dist2 = $this->distanceBetweenTwoGeoPoints($lat2, $lng2, $lat3, $lng3);
//        $dist3 = $this->distanceBetweenTwoGeoPoints($lat1, $lng1, $lat3, $lng3);
//
//        $speed1 = $dist1 / ($ts2 - $ts1) * 3600;
//        $speed2 = $dist2 / ($ts3 - $ts2) * 3600;
//
//        print "\n1 to 2: $dist1 km, $speed1 km/h";
//        print "\n2 to 3: $dist2 km, $speed2 km/h";
//        print "\n1 to 3: $dist3 km";
//
//        if ($dist1 != 0 && $dist2 != 0 && $dist3 != 0) {
//            $angles = $this->solveThreeSidedTriangle($dist1, $dist2, $dist3);
//
//            print "\nAngle at 1: {$angles[0]} deg";
//            print "\nAngle at 2: {$angles[1]} deg";
//            print "\nAngle at 3: {$angles[2]} deg";
//        }
//
//        print "\n\n";
//    }

}


/**
 * Register all concrete Reader and Writer types.
 */
foreach (get_declared_classes() as $className) {
    if (in_array('Reader', class_implements($className))) {
        ReaderFactory::instance()->register($className);
    }

    if (in_array('Writer', class_parents($className))) {
        WriterFactory::instance()->register($className);
    }
}


// Start the app!
RouteCleaner::instance()->main($argv);



