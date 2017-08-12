# MailParser 0.2
PHP MailParser, no extensions required, converts raw mails into simple objects. Minimum version of PHP is **5.4** because I used a short array syntax.

> If you are having trouble with a site that sends mails with parse them - post your problem in issues with a raw part that is incorrectly parsed

## Methods
### __construct($raw='')
- `$raw` string

### setRaw($raw='')
- `$raw` string

Overwrite raw version of mail

### getRaw()
Returns raw version of mail

### setWhitelist($list)
- `$list` array

Overwrite whitelisted HTML tags

### getWhitelist()
Returns array with whitelisted tags.
Default whitelist contains:<br>
`a`, `abbr`, `acronym`, `address`, `area`, `b`, `bdo`, `big`, `blockquote`, `br`, `button`, `caption`, `center`, `cite`, `code`, `col`, `colgroup`, `dd`, `del`, `dfn`, `dir`, `div`, `dl`, `dt`, `em`, `fieldset`, `font`, `form`, `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `hr`, `i`, `img`, `input`, `ins`, `kbd`, `label`, `legend`, `li`, `map`, `menu`, `ol`, `optgroup`, `option`, `p`, `pre`, `q`, `s`, `samp`, `select`, `small`, `span`, `strike`, `strong`, `sub`, `sup`, `table`, `tbody`, `td`, `textarea`, `tfoot`, `th`, `thead`, `u`, `tr`, `tt`, `u`, `ul`, `var`

### pushWhitelist(...)
- `...` [greedy](#greedy-arguments) string

Push any number of arguments into whitelist of HTML tags

### removeWhitelist(...)
- `...` [greedy](#greedy-arguments) string

Remove any number of arguments from whitelist of HTML tags

### setAllowedMIMEsS($list)
- `$list` array

Overwrite whitelisted MIMES of attachments

### getAllowedMIMEs()
Returns array with whitelisted MIMEs of attachments.
Default contains:<br>
`application/zip`, `image/tiff`, `application/x-compressed`, `application/x-tar`, `application/pdf`, `video/mpeg`, `audio/mpeg`, `image/jpeg`, `application/msword`, `image/bmp`, `image/png`, `image/gif`

### pushAllowedMIMEs(...)
- `...` [greedy](#greedy-arguments) string

Push any number of arguments into whitelist of attachments MIMEs

### removeAllowedMIMEs(...)
- `...` [greedy](#greedy-arguments) string

Remove any number of arguments from whitelist of HTML tags

### setHeader($name, $value)
- `$name` string
- `$value` string

Parse and set header into main context

### parseHeader(&$h, $name, $value)
- `&$h` array - Container for headers
- `$name` string
- `$value` string

Transform some headers into more readable version and save them into container. List of transitions:

| Header name | Transition |
|------:|----|
| `to`, `from` | Array with the number of sub-arrays equal to the number of addresses in the string.<br>Each element of this array has two keys: `name` and `mail`.<br>The structure looks like this: `[['name'=>'','mail'=>'']*]`. |
| `date` | Will be represented as UNIX timestamp |
| `content-type` | First found MIME (e.g `image/png`) will be saved as `content-type`.<br>`boundary` will be saved as named if found, otherwise will be filled with `false`.<br>`name` will be saved as `filename`.<br> `charset` will be saved same as named.<br>Rest of string will be ignored. |
| `content-disposition` | If keyword `attachment` fill be found then `is-attachment` will be `true`.<br>`name` will be saved as `filename`.<br>`content-disposition` is saved unchanged. |

Other headers will be rewritten without changes.
**All headers are in lowercase.**

### parseHeaders(&$content)
- `&$content` string - Raw part of mail

This method split string into the header and body of the mail.  `$content` is overwritten by body. The header is splits, parsed, and returned as an array.

### parseAddresses($value)
- `$value` string

Transform and returns list of addresses e.g: `mark@domain.com, Mark Steve <marks@gmail.com>` as array:
```php
[
  [
    'name' => '',
    'mail' => 'mark@domain.com'
  ],
  [
    'name' => 'Mark Steve',
    'mail' => 'marks@gmail.com'
  ]
]
```
### parseNonASCII($value)
- `$value` string

Transform and returns encoded text as UTF-8 e.g `=?utf-8?Q?Maciej_Mie=C5=84ko?=` will be transformed into `Maciej Mieńko`. Strings are encoded when they contain non-ASCII characters - hence the name

### parseBody($headers, $content)
- `$headers` array - Returned by `parseHeaders`
- `$content` string

Splits given string by the bounders (if any) and repeat it for each part separately - specifying new sub-headers, encoding, etc.
For MIME 'text/html' removes non-whitelist tags and 'text/plain' escapes HTML characters.
If MIME is not one from two above and in headers non found informations about attachments then given content will be discarded at end of all processes. 

### parse()
Run parsing and clear raw

### getBodies()
Returns list of all contexts:
```php
[
  [
    'headers' => [
      'content-type' => 'text/plain'
    ],
    'body' => 'text'
  ],
  // ...
]
```

### getBody($mime, $dummy=false)
- `$mime` string
- `$dummy` bool

Returns first found body with given mime. Otherwhise returns `false`.

If `$dummy` is true then instead of `false` during failure returns dummy data:
```php
[
  'headers' => [
    'content-type' => $mime
  ],
  'body' => ''
]
```

### getAttachments($allowed=true)
- `$allowed` bool

Returns all of attachments from bodies. If `$allowed` is set as `true`, then returns only attachments with MIMEs located in list with allowed MIMEs.

### getHeaders()
**All headers are in lowercase.**
Returns headers from main context
```php
[
  'to' => [...],
  'from' => [...],
  'content-type' => 'text/plain',
  // ...
]
```

### getFrom()
Returns nice looking `from` header e.g `mark@domain.com, Mark Steve <marks@gmail.com>`

### getTo()
Returns nice looking `To` header e.g `mark@domain.com, Mark Steve <marks@gmail.com>`

### getSubject($alt='')
- `$alt` string

If exists returns header named `subject`, otherwise returns given argument.

## Greedy arguments
Greedy arguments means all nested values will be treated as normal arguments. For example:
```php
$parser->setWhitelist('ul',['ol',['a',['b'],['td',['pre'],'small']]]);
```
will be treated the same as
```php
$parser->setWhitelist('ul','ol','a','b','td','pre','small');
```

## TODO
- [ ] Automate some tasks (eg save attachments)
- [ ] Allow set custom parsing functions for each MIME/header
