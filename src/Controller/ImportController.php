<?php

namespace Bolt\Extension\Bolt\Importwxr\Controller;

use Bolt\Extension\Bolt\Importwxr\WXR_Parser;
use Bolt\Filesystem\Exception\IOException;
use Bolt\Storage\Entity;
use Maid\Maid;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ImportController implements ControllerProviderInterface
{
    /** @var Application $app */
    protected $app;

    /** @var array $config */
    protected $config;

    /** @var array $foundcategories */
    protected $foundcategories = [];

    /** @var array $linkmapping */
    protected $linkmapping = [];

    /** @var string $base_url */
    protected $base_url = '';

    /** @var array $foundimages */
    protected $foundimages = [];

    /** @var array $imagemapping */
    protected $imagemapping = [];

    /**
     * ProtectController constructor.
     *
     * @param Application $app
     * @param array $config
     */
    public function __construct(Application $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * @param Application $app
     * @return mixed
     */
    public function connect(Application $app)
    {
        $controller = $app['controllers_factory'];

        $controller->match('/', [$this, 'importWXR']);
        // $controller->match('/changePassword', [$this, 'changePassword']);

        //This must be ran, current user is not set at this time.
        $controller->before([$this, 'before']);
        return $controller;
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return null|RedirectResponse
     */
    public function before(Request $request, Application $app)
    {
        if (!$app['users']->isAllowed('dashboard')) {
            /** @var UrlGeneratorInterface $generator */
            $generator = $app['url_generator'];
            return new RedirectResponse($generator->generate('dashboard'), Response::HTTP_SEE_OTHER);
        }
        return null;
    }

    public function importWXR()
    {
        $filesystem = $this->app['filesystem']->getFilesystem('config');
        $file = $filesystem->getFile($this->config['file']);
        $fileAbsolutePath = $filesystem->getAdapter()->getPathPrefix() . $file->getPath();

        // No logging. saves memory..
        $this->app['db.config']->setSQLLogger(null);

        if (!empty($_GET['action'])) {
            $action = $_GET['action'];
        } else {
            $action = "start";
        }

        switch ($action) {
            case "start":
                $output = $this->actionStart($filesystem, $file);
                break;

            case "confirm":
                $output = $this->actionImport($fileAbsolutePath);
                break;

            case "authors":
                $output = $this->actionAuthors($fileAbsolutePath);
                break;

            case "dryrun":
                $output = $this->actionDryRun($fileAbsolutePath);
        }

        $output = '<div class="row"><div class="col-md-8">' . $output . "</div></div>";

        return $this->app['render']->render('@importwxr/import.twig', array(
            'title' => "Import WXR (PivotX / Wordpress XML)",
            'output' => $output
        ));
    }

    private function actionStart($filesystem, $file)
    {
        $output = '';

        try {
            if ($filesystem->has($file->getPath())) {
                $filesystem->read($file->getPath());

                $output .= sprintf("<p>File <code>%s</code> selected for import.</p>", $this->config['file']);

                $output .= "<p><a class='btn btn-primary' href='?action=dryrun'><strong>Test a few records</strong></a>";
                $output .= "&nbsp; <a class='btn btn-primary' href='?action=authors'><strong>Import Authors</strong></a></p>";

                $output .= "<p>This mapping will be used:</p>";
                $output .= $this->dump($this->config['mapping']);

                return $output;
            } else {
                // show does not exist message
                $output = "<p>File $file doesn't exist. Correct this in <code>app/config/extensions/importwxr.bolt.yml</code>, and refresh this page.</p>";

                return $output;
            }
        } catch (IOException  $e) {
            // show is not readable message
            $output = "<p>File " . $file->getPath() . " Is not readable. Set readable permission to this file and refresh this page.</p>";

            return $output;
        }
    }

    private function actionImport($fileAbsolutePath)
    {
        $output = '';

        $parser = new WXR_Parser();
        $res = $parser->parse($fileAbsolutePath);

        foreach ($res['posts'] as $post) {
            $output .= $this->importPost($post, false);
        }

        $this->importImages();

        if (!empty($this->foundcategories)) {
            $cat_array = array(
                "categories" => array(
                    'options' => $this->foundcategories
                )
            );
            $cat_yaml = \Symfony\Component\Yaml\Yaml::dump($cat_array, 3);
            $output .= "<br><p>These categories were found, make sure you add them to your <code>taxonomy.yml</code></p>";
            $output .= "<textarea style='width: 700px; height: 200px;'>" . $cat_yaml . "</textarea>";
        }

        $output .= $this->makeLinkTable();

        $output .= "<br><br><p><strong>Imported images " . count($this->imagemapping)
            ." out of " . count($this->foundimages) . " evaluated:</strong><br>"
            . implode("<br>", $this->imagemapping) . "</p>";

        $output .= "<p><strong>Done!</strong></p>";

        return $output;
    }

    private function actionAuthors($fileAbsolutePath)
    {
        $output = '';

        $parser = new WXR_Parser();
        $res = $parser->parse($fileAbsolutePath);

        foreach ($res['authors'] as $author) {
            $output .= $this->importAuthor($author);
        }

        $output .= "<p><strong>Done!</strong></p>";

        return $output;
    }

    private function actionDryRun($fileAbsolutePath)
    {
        $output = '';
        $counter = 1;

        $parser = new WXR_Parser();
        $res = $parser->parse($fileAbsolutePath);

        $this->base_url = $res['base_url'];

        foreach ($res['posts'] as $post) {
            $result = $this->importPost($post, true);
            if ($result != false) {
                $output .= $result;
                if ($counter++ >= 4) {
                    break;
                }
            }
        }

        $output .= sprintf("<p>Looking good? Then click below to import the Records: </p>");
        $output .= "<p><a class='btn btn-primary' href='?action=confirm'><strong>Confirm!</strong></a></p>";

        return $output;
    }

    private function setOwnerID($post, $mapping)
    {
        if (!empty($mapping['author'])) {
            $author = $mapping['author'];
        } else {
            $author = $post['post_author'];
        }

        $usersrepo = $this->app['storage']->getRepository('Bolt\Storage\Entity\Users');
        $user = $usersrepo->getUser($author);

        return ($user ? $user->getId() : null);
    }

    private function importAuthor($author)
    {
        $usersrepo = $this->app['storage']->getRepository('Bolt\Storage\Entity\Users');

        $user = $usersrepo->getUser($author['author_login']);

        if (!empty($user)) {
            return "User " . $author['author_login'] . " is already defined. Skipping. <br>";
        }

        $user = new Entity\Users();

        $user->setEmail($author['author_email']);
        $user->setUsername($author['author_login']);
        $user->setEnabled(true);
        $user->setDisplayname($author['author_display_name']);
        $user->setPassword(bin2hex(random_bytes(16)));

        $usersrepo->save($user);

        return "User " . $author['author_login'] . " has been imported. <br>";
    }

    private function importPost($post, $dryrun = true)
    {
        // If the mapping is not defined, ignore it.
        if (empty($this->config['mapping'][$post['post_type']])) {
            if ($dryrun) {
                return false;
            } else {
                return "<p>No mapping defined for posttype '" . $post['post_type'] . "'.</p>";
            }
        }

        // Find out which mapping we should use.
        $mapping = $this->config['mapping'][$post['post_type']];

        // If the mapped contenttype doesn't exist in Bolt.
        if (!$this->app['storage']->getContentType($mapping['targetcontenttype'])) {
            if ($dryrun) {
                return false;
            } else {
                return "<p>Bolt contenttype '" . $mapping['targetcontenttype'] . "' for posttype '" . $post['post_type'] . "' does not exist.</p>";
            }
        }

        // Create the new Bolt Record.
        $record = new \Bolt\Content($this->app, $mapping['targetcontenttype']);

        // 'expand' the postmeta fields to regular fields.
        if (!empty($post['postmeta']) && is_array($post['postmeta'])) {
            foreach ($post['postmeta'] as $id => $keyvalue) {
                $post[$keyvalue['key']] = $keyvalue['value'];
            }
        }

        // Convert some [shorttags] to HTML in the content..
        $post = $this->convertShorttags($post);

        if (empty($mapping['image_prefix'])) {
            $mapping['image_prefix'] = "wxrimport";
        }

        // Find images, and replace them in the content.
        $post = $this->collectImages($post, $mapping['image_prefix']);

        // Iterate through the mappings, see if we can find it.
        foreach ($mapping['fields'] as $from => $to) {
            if (isset($post[$from])) {
                // It's present in the fields.

                $value = $post[$from];

                switch ($from) {
                    case "post_parent":
                        if (!empty($value)) {
                            $value = $mapping['fields']['post_parent_contenttype'] . "/" . $value;
                        }
                        break;
                    case "post_date":
                        if (!empty($value)) {
                            // WXR seems to use only one date value.
                            $record->setValue('datechanged', $value);
                            $record->setValue('datecreated', $value);
                            $record->setValue('datepublish', $value);
                        }
                        break;
                }

                switch ($to) {
                    case "status":
                        if ($value == "publish" || $value == "inherit") {
                            $value = "published";
                        }
                        if ($value == "future") {
                            $value = "timed";
                        }
                        break;
                    case "image":
                        if (is_string($value) && !empty($value)) {
                            $filename = sprintf(
                                "%s/%s/%s",
                                $mapping['image_prefix'],
                                substr($post['post_date'], 0, 7),
                                basename($value)
                            );
                            $value = json_encode(['file' => $filename]);
                        }
                        break;
                }

                $record->setValue($to, $value);
            }
        }

        $record->setValue('ownerid', $this->setOwnerID($post, $mapping));

        // make sure we have a sensible slug
        if (!empty($post['post_name'])) {
            $slug = $post['post_name'];
        } else {
            $slug = $post['post_title'];
        }
        $record->setValue('slug', $this->app['slugify']->slugify($slug));

        // Perhaps import the categories as well..
        // TODO: Import properly..
        if (!empty($mapping['category']) && !empty($post['terms'])) {
            foreach ($post['terms'] as $term) {
                if ($term['domain'] == 'category') {
                    // dump($post['post_id'] . " - category = " . $term['slug'] );
                    $record->setTaxonomy($mapping['category'], $term['slug'], $term['name']);
                    if (!in_array($term['slug'], $this->foundcategories)) {
                        $this->foundcategories[$term['slug']] = $term['name'];
                    }
                }
                if ($term['domain'] == 'event-categories') {
                    $record->setTaxonomy($mapping['tags'], $term['slug'], $term['slug']);
                }
                if ($term['domain'] == 'tag' || $term['domain'] == 'post_tag') {
                    // dump("tag = " . $term['slug'] );
                    $record->setTaxonomy($mapping['tags'], $term['slug'], $term['name']);
                }
            }
        }

        if ($dryrun) {
            $output = "<p>Original WXR Post <b>\"" . $post['post_title'] . "\"</b> -&gt; Converted Bolt Record :</p>";
            $output .= $this->dump($post);
            $output .= $this->dump($record);
            $output .= "\n<hr>\n";
        } else {
            $id = $this->upsert($record);
            $output = "Import: " . $id . " - " . $record->get('title') . " <small><em>";
            $output .= $this->memUsage() . "mb.</em></small><br>";

            $this->linkmapping[] = [
                'id' => $record->id,
                'status' => $record->get('status'),
                'old' => $post['link'],
                'new' => $record->link()
            ];
        }

        return $output;
    }

    private function upsert($record)
    {
        // We check if record[id] is not empty, and if it already exists. If not, we create a stub,
        // so 'savecontent' won't fail.
        if (!empty($record['id'])) {
            $temp = $this->app['storage']->getContent($record->contenttype['slug'] . '/' . $record['id']);
            if (empty($temp)) {
                // dump($this->app['config']->get('general'));
                $tablename = $this->app['config']->get('general/database/prefix') . $record->contenttype['tablename'];
                $sql = "INSERT INTO `$tablename` (`id`) VALUES (" . $record['id'] . ");";
                $this->app['db']->query($sql);
            }
        }

        $id = $this->app['storage']->saveContent($record);

        return $id;
    }

    private function makeLinkTable()
    {
        $output = "<br><p>These links were found, you can use them to make redirects from the old site to the new one:</p>";
        $output .= "<table border='1' width='1500' cellspacing='0' cellpadding='3'><tr><th>ID</th><th>Status</th><th>Original Link</th><th>New Link</th></tr>";

        foreach ($this->linkmapping as $item) {
            $parse = parse_url($item['old']);
            $old_pretty = $parse['path'] . (!empty($parse['query']) ? '?' . $parse['query'] : '');
            $output .= sprintf("<tr><td>%s</td>", $item['id']);
            $output .= sprintf("<td>%s</td>", $item['status']);
            $output .= sprintf("<td><a href='%s'>%s</a></td>", $item['old'], $old_pretty);
            $output .= sprintf("<td><a href='%s'>%s</a></td></tr>", $item['new'], $item['new']);
        }
        $output .= "</table>";

        return $output;
    }

    private function collectImages($post, $prefix)
    {
        $html = [];

        foreach (['attachment_url', 'post_content', 'post_excerpt' ] as $name) {
            if (isset($post[$name])) {
                $html[] = $post[$name];
            }
        }
        $html = implode(' ', $html);

        // Deprecated:
        $path = $this->app['paths']['files'];

        $pattern = '/\bhttps?:\/\/\S+(?:png|jpg)\b/i';
        $res = preg_match_all($pattern, $html, $matches);

        if ($res) {
            foreach ($matches[0] as $oldname) {
                $this->app['slugify']->setRegExp(['regexp' => '/([^A-Za-z0-9_\.]|-)+/']);
                $basename = $this->app['slugify']->slugify(basename($oldname));
                $newname = sprintf('%s/%s/%s', $prefix, substr($post['post_date'], 0, 7), $basename);
                $this->foundimages[$oldname] = $newname;
                $post['post_excerpt'] = str_replace($oldname, $path . $newname, $post['post_excerpt']);
                $post['post_content'] = str_replace($oldname, $path . $newname, $post['post_content']);

                if (empty($post['image'])) {
                    $post['image'] = ["file" => $newname];
                }
            }
        }

        return $post;
    }

    private function importImages()
    {
        if (!$this->config['fetch_images']) {
            return false;
        }

        /** @var \Bolt\FileSystem\Filesystem $files */
        $files = $this->app['filesystem']->getFilesystem('files');

        $this->imagemapping = [];

        $this->foundimages = array_reverse($this->foundimages);

        foreach ($this->foundimages as $old => $new) {
            if ($this->config['max_images'] <= 0) {
                break;
            }
            if (!$files->has($new)) {
                try {
                    $res = $this->app['guzzle.client']->request('GET', $old);
                    $files->put($new, $res->getBody());
                    $this->imagemapping[] = "Fetched: <tt>$old</tt> -> <tt>$new</tt>";
                    $this->config['max_images']--;
                } catch (\Exception $e) {
                    $this->imagemapping[] = "Failed to fetch: <tt>$old</tt>";
                }
            } else {
                // $this->imagemapping[] = "Skipped: <tt>$new</tt>";
            }
        }
    }


    private function convertShorttags($record)
    {
        // dump($record['post_content']);
        $record['post_content'] = preg_replace_callback(
            '/\[caption([^\]]*)\](.*)\[\/caption\]/i',
            [$this, 'convertShorttagCaption'],
            $record['post_content']
        );

        if (strpos($record['post_content'], '[gallery') !== false) {
            $record['status'] = "draft";
            $record['post_title'] = "[FIXME] " . $record['post_title'];
        }
        // dump($record['post_content']);
        // echo "<hr>";
        return $record;
    }

    /**
     * Convert WP [caption]
     * from: [caption caption="foo"]<a href="x"><img class="size-full wp-image-1717" src="bar.jpg" /></a>[/caption]
     * to: <p class='image-with-caption'><a href='x'><img src='bar.jpg'></a><em>foo</em></p>
     */
    public function convertShorttagCaption($arr)
    {
        $arguments = $this->regexArguments($arr[1]);

        // If the caption is not an 'attribute' in the `[caption]` tag, glean it
        // from the contents of the tag.
        if (empty($arguments['caption'])) {
            $arguments['caption'] = strip_tags($arr[2]);
            $arr[2] = str_replace($arguments['caption'], '', $arr[2]);
        }

        $res = sprintf(
            "<p class='image-with-caption'>%s<em>%s</em></p>",
            $this->cleanHTML($arr[2]),
            !empty($arguments['caption']) ? $arguments['caption'] : ''
        );

        return $res;
    }

    /**
     * Convert WP [caption]
     * from: [caption caption="foo"]<a href="x"><img class="size-full wp-image-1717" src="bar.jpg" /></a>[/caption]
     * to: <p class='image-with-caption'><a href='x'><img src='bar.jpg'></a><em>foo</em></p>
     */
    public function convertShorttagGallery($arr)
    {
        $arguments = $this->regexArguments($arr[1]);

        // TODO: Needs to be implemented;
        return;

        $res = sprintf(
            "<p class='image-with-caption'>%s<em>%s</em></p>",
            $this->cleanHTML($arr[2]),
            !empty($arguments['caption']) ? $arguments['caption'] : ''
        );

        return $res;
    }


    public function cleanHTML($html)
    {
        $maid = new Maid(
            [
                'output-format'   => 'html',
                'allowed-tags'    => $this->config['allowed_tags'],
                'allowed-attribs' => $this->config['allowed_attributes']
            ]
        );

        return $maid->clean($html);
    }

    public function regexArguments($arg)
    {
        $res = preg_match_all('/(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|["\']))+.)["\']?/i', $arg, $matches);
        $arguments = [];

        if ($res) {
            foreach ($matches[1] as $key => $value) {
                $arguments[$value] = $matches[2][$key];
            }
        }

        return $arguments;
    }


    private function memusage()
    {
        $mem = number_format(memory_get_usage() / 1048576, 1);

        return $mem;
    }

    private function dump($var)
    {
        ob_start();
        dump($var);
        return ob_get_clean();
    }
}
