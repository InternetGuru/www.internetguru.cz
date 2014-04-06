/**
 * Create DOM from XML config files in folowing order
 *
 * @VARIABLE: String plugin (optional)
 * @USAGE: $myCfg = new Config("slider");
 */
class Config {

  __construct($plugin="default") {

    if(!is_string($plugin)) throw new Exception('Variable type: not string.');

    // create DOM from default config xml
    $cfg = new DOMDocument();
    if($plugin == "default") $cfg->load("default.xml");
    else $cfg->load("plugins/$plugin/$plugin.xml");

    // modify DOM by specific domain data
    #TODO
    
    // modify DOM by user data (except readonly)
    #TODO

  }

}