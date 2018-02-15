<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Configuration parameters for the ciocase library for CodeIgniter
 *
 * @package   cingo
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


/**
  * The name of the database
  *
  * The value must matched a backend defined in application/models
  *
  * @var string
  */
$config['backend'] = NULL;


/**
  * The name of the collection
  *
  * The value must matched a backend defined in application/models
  *
  * @var string
  */
$config['collection'] = NULL;


/**
  * The date the backend was last updated as YYYY-MM-DD
  *
  * Not great, but there is currently no programmatic way to get this data for
  * NMNH that I know of.
  *
  * @var string
  */
$config['date_updated'] = '';


/**
  * The default query as an associative array
  *
  * All queries must match the parameters defined here
  *
  * @var array
  */
$config['defaults'] = [];


/**
  * Default search is ignored when searching these fields
  *
  * @var array
  */
$config['ignore_defaults'] = [];


/**
  * The BioCASE concept mapping files used by this application
  *
  * The JSON files used by this application are generated from the master
  * concept mapping files included with the BioCASE Provider Tool. The path
  * to each file is relative to the site root.
  *
  * @var array
  */
$config['concept_mapping_files'] = [
  base_url('files/cmf/cmf_ABCD_2.06.json'),
  base_url('files/cmf/cmf_ABCDEFG_2.06.json'),
  base_url('files/cmf/cmf_DwC_2015-03-19.json'),
  base_url('files/cmf/cmf_SimpleDwC_2015-03-19.json')
];


/**
  * Maps inheritance between schemas
  *
  * @var array
  */
$config['bequests'] = [
  'http://www.tdwg.org/schemas/abcd/2.06' => (object) [
      'url' => 'http://www.synthesys.info/ABCDEFG/1.0',
      'replacements' => NULL
    ],
  'http://rs.tdwg.org/dwc/dwcrecord/' => (object) [
      'url' => 'http://rs.tdwg.org/dwc/xsd/simpledarwincore/',
      'replacements' => [
        '/\/DarwinRecordSet\/[A-z]+?\//' => '/SimpleDarwinRecordSet/SimpleDarwinRecord/',
      ]
    ]
];


/**
  * The list of concepts defined for this application
  *
  * Each concept corresponds to a data element defined in one of the schema
  * files. The path must appear in the associated concept mapping file.
  *
  * The arguments for each Concept are:
  *   url (string): the url of the schema
  *   path (string): the path to the field in the schema. If this ends with a
  *       plus (+), then new values will be appended to that container.
  *   mapping (mixed): the mapping from the database. This can be a field
  *       name, a list of field names, an associative array mapping fields
  *       to keys, a formatting string, or a [[verbatim value]].
  *   criteria (array): Optional. The criteria a record must match to trigger
  *       this concept.
  *
  * @var array
  */
$config['concepts'] = [

  #new Concept(url, path, mapping, criterion)

  // Check a single, atomic field
  new Concept('http://www.tdwg.org/schemas/abcd/2.06',
              '/DataSets/DataSet/Units/Unit/UnitGUID',
              'guid'),

  // Check multiple fields for a path that accepts only one value. Any values
  // found will be returned concatenated by a pipeline.
  new Concept('http://www.tdwg.org/schemas/abcd/2.06',
              '/DataSets/DataSet/Units/Unit/UnitID',
               ['field', 'field.subfield']),


  // Map verbatim values multiple paths under the container given by path
  new Concept('http://www.tdwg.org/schemas/abcd/2.06',
              '/DataSets/DataSet/TechnicalContacts/TechnicalContact',
              ['Name' => '[[Adam Mansur]]', 'Email' => '[[mansura@si.edu]]']),


  // Map a verbatim value to a path if the record meets the given criertia
  new Concept('http://www.tdwg.org/schemas/abcd/2.06',
              '/DataSets/DataSet/Units/Unit/SourceID',
              '[[Mineral Sciences]]',
              ['key' => 'val']),

  // Map one verbatim and one database value under a new instance of NamedArea.
  // The plus on the path indicates to appened a new container.
  new Concept('http://www.tdwg.org/schemas/abcd/2.06',
              '/DataSets/DataSet/Units/Unit/Gathering/NamedAreas/NamedArea+',
              ['AreaClass' => '[[State/Province]]', 'AreaName' => 'state']),

  // Map an array of values, each as a new instance of the container. The
  // Value class allows you to apply a formatting mask. In this case, the
  // value for the doi key in each ref will be subbed into the formatted
  // string where indicated by the curly braces.
  new Concept('http://www.tdwg.org/schemas/abcd/2.06',
              '/DataSets/DataSet/Units/Unit/UnitReferences/UnitReference+',
              ['Title' => 'refs.title',
               'URI' => new Value('refs.doi', 'https://doi.org/{}')]),

  // Map a value using a formatting string. Unlike the previous example, this
  // formulation only considers the first match in the refs array.
  new Concept('http://www.tdwg.org/schemas/abcd/2.06',
              '/DataSets/DataSet/Units/Unit/UnitReferences/UnitReference',
              'https://doi.org/{refs.doi}')
];


/**
  * Query parameters
  *
  * The available keys for each query parameter are:
  *   mixed  key        the name of the database key or keys OR the name of a
  *                     a control parameter (like limit or format) used to
  *                     set up a query
  *   string definition a brief description of the param
  *   array  options    a list of acceptable values for a control parameter
  *   array  mapping    maps query string to database query
  *   array  default    the default value for a control parameter
  *
  * @var array
  */
$config['query_params'] = [
  'search_one_field' => [
    'keys' => 'key',
    'definition' => 'definition of this field'
  ],
  'search_multiple_fields' => [
    'keys' => ['key_1', 'key_2'],
    'definition' => 'definition of this field'
  ],
  'map_search' => [
      'keys' => 'bool_key',
      'definition' => 'definition of this field',
      'options' => ['true', 'false'],
      'mapping' => ['true' => '1', 'false' => '0']
  ],
  // The fields from here on down are not related to the database
  'schema' => [
      'keys' => 'schema',
      'definition' => 'the abbreviation for an available schema',
      'options' => ['abcd', 'abcdefg', 'dwr', 'simpledwr'],
      'default' => 'abcdefg'
    ],
  'limit' => [
      'keys' => 'limit',
      'definition' => 'the number of records to return',
      'default' => 100
    ],
  'offset' => [
      'keys' => 'offset',
      'definition' => 'the index of the first record to return',
      'default' => 0
    ],
  'format' => [
    'keys' => 'format',
    'definition' => 'the format in which to return the data',
    'options' => ['html', 'json', 'xml'],
    'default' => 'html'
    ],
  'bcp' => [
    'keys' => 'bcp',
    'definition' => 'specifies whether to include the BioCASE protocol wrapper. Ignored for HTML requests.',
    'options' => ['true', 'false'],
    'default' => 'true'
  ]
];


/**
  * Query parameters
  *
  * A list of query params to ignore when searching. By default, certain
  * fields used by BioCASe are not used.
  *
  */
$config['map_or_ignore'] = []
  'dsa' => NULL,
  'inst' => NULL,
  'col' => NULL,
  'key_x' => 'key_1'
];

?>
