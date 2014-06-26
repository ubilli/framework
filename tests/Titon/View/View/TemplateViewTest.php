<?php
namespace Titon\View\View;

use Titon\Cache\Storage\FileSystemStorage;
use Titon\View\View;
use Titon\Test\TestCase;

/**
 * @property \Titon\View\View\TemplateView $object
 */
class TemplateViewTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        $this->object = new TemplateView([
            TEMP_DIR,
            TEMP_DIR . '/fallback'
        ]);
    }

    public function testRender() {
        $this->assertEquals('<layout>edit.tpl</layout>', $this->object->render(['index', 'edit']));

        $this->object->getEngine()->useLayout('fallback');
        $this->assertEquals('<fallbackLayout>add.tpl</fallbackLayout>', $this->object->render(['index', 'add']));

        $this->object->getEngine()->wrapWith('wrapper');
        $this->assertEquals('<fallbackLayout><wrapper>index.tpl</wrapper></fallbackLayout>', $this->object->render(['index', 'index']));

        $this->object->getEngine()->wrapWith(['wrapper', 'fallback']);
        $this->assertEquals('<fallbackLayout><fallbackWrapper><wrapper>view.tpl</wrapper></fallbackWrapper></fallbackLayout>', $this->object->render(['index', 'view']));

        $this->object->getEngine()->wrapWith(false)->useLayout(false);
        $this->assertEquals('view.xml.tpl', $this->object->render(['index', 'view', 'ext' => 'xml']));
    }

    public function testRenderPrivate() {
        $this->assertEquals('<layout>public/root.tpl</layout>', $this->object->render('root'));
        $this->assertEquals('<layout>private/root.tpl</layout>', $this->object->render('root', true));
    }

    public function testRenderTemplate() {
        $this->assertEquals('add.tpl', $this->object->renderTemplate($this->object->locateTemplate(['index', 'add'])));
        $this->assertEquals('test-include.tpl nested/include.tpl', $this->object->renderTemplate($this->object->locateTemplate(['index', 'test-include'])));

        // variables
        $this->assertEquals('Titon - partial - variables.tpl', $this->object->renderTemplate($this->object->locateTemplate('variables', View::PARTIAL), [
            'name' => 'Titon',
            'type' => 'partial',
            'filename' => 'variables.tpl'
        ]));
    }

    public function testViewCaching() {
        $storage = new FileSystemStorage(['directory' => TEMP_DIR . '/cache/']);
        $this->object->setStorage($storage);

        $path = $this->object->locateTemplate(['index', 'test-include']);
        $key = md5($path);
        $cachePath = TEMP_DIR . '/cache/' . $key . '.cache';

        $this->assertEquals('test-include.tpl nested/include.tpl', $this->object->renderTemplate($path));
        $this->assertFileNotExists($cachePath);

        $this->assertEquals('test-include.tpl nested/include.tpl', $this->object->renderTemplate($path, ['cache' => '+5 minutes']));
        $this->assertFileExists($cachePath);
        $mtime = filemtime($cachePath);

        $this->assertEquals('test-include.tpl nested/include.tpl', $this->object->renderTemplate($path, ['cache' => '+5 minutes']));
        $this->assertEquals($mtime, filemtime($cachePath));

        $storage->flush();
    }

}