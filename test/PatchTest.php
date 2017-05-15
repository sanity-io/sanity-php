<?php
namespace SanityTest;

use Sanity\Selection;
use Sanity\Patch;

class PatchTest extends TestCase
{
    public function testCanConstructNewPatchWithUninitializedSelection()
    {
        $this->assertInstanceOf(Patch::class, new Patch('abc123'));
    }

    public function testCanConstructNewPatchWithInitializedSelection()
    {
        $selection = new Selection('abc123');
        $this->assertInstanceOf(Patch::class, new Patch($selection));
    }

    public function testCanConstructNewPatchWithInitialOperations()
    {
        $patch = new Patch('abc123', ['inc' => ['count' => 1]]);
        $this->assertEquals(['id' => 'abc123', 'inc' => ['count' => 1]], $patch->serialize());
    }

    public function testCanCreateMergePatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->merge(['foo' => 'bar']));
        $this->assertEquals(['id' => 'abc123', 'merge' => ['foo' => 'bar']], $patch->serialize());
    }

    public function testMergesWhenMultipleMergeOperationsAdded()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->merge(['foo' => 'bar']));
        $this->assertSame($patch, $patch->merge(['bar' => 'baz']));
        $this->assertSame($patch, $patch->merge(['foo' => 'moo']));
        $this->assertEquals(
            ['id' => 'abc123', 'merge' => ['foo' => 'moo', 'bar' => 'baz']],
            $patch->serialize()
        );
    }

    public function testCanCreateSetPatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->set(['foo' => 'bar']));
        $this->assertEquals(['id' => 'abc123', 'set' => ['foo' => 'bar']], $patch->serialize());
    }

    public function testCanCreateDiffMatchPatch()
    {
        $diff = '@@ -21,4 +21,10 @@\n-jump\n+somersault\n';
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->diffMatchPatch(['body' => $diff]));
        $this->assertEquals(['id' => 'abc123', 'diffMatchPatch' => ['body' => $diff]], $patch->serialize());
    }

    public function testCanCreateRemovePatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->remove(['foo', 'bar']));
        $this->assertEquals(['id' => 'abc123', 'unset' => ['foo', 'bar']], $patch->serialize());
    }

    /**
     * @expectedException Sanity\Exception\InvalidArgumentException
     * @expectedExceptionMessage array of attributes
     */
    public function testThrowsWhenCallingRemoveWithoutArray()
    {
        $patch = new Patch('abc123');
        $patch->remove('foobar');
    }

    public function testCanCreateReplacePatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->replace(['foo' => 'bar']));
        $this->assertEquals(['id' => 'abc123', 'set' => ['$' => ['foo' => 'bar']]], $patch->serialize());
    }

    public function testReplacePatchOverridesPreviousSetPatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->set(['bar' => 'baz']));
        $this->assertSame($patch, $patch->replace(['foo' => 'bar']));
        $this->assertEquals(['id' => 'abc123', 'set' => ['$' => ['foo' => 'bar']]], $patch->serialize());
    }

    public function testCanCreateIncPatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->inc(['count' => 1]));
        $this->assertEquals(['id' => 'abc123', 'inc' => ['count' => 1]], $patch->serialize());
    }

    public function testCanCreateDecPatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->dec(['count' => 1]));
        $this->assertEquals(['id' => 'abc123', 'dec' => ['count' => 1]], $patch->serialize());
    }

    /**
     * @expectedException Sanity\Exception\ConfigException
     * @expectedExceptionMessage mutate() method
     */
    public function testThrowsWhenCallingCommitWithoutClientContext()
    {
        $patch = new Patch('abc123');
        $patch->remove(['foo']);
        $patch->commit();
    }

    public function testCanResetPatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->dec(['count' => 1])->inc(['count' => 2]));
        $this->assertEquals(
            ['id' => 'abc123', 'dec' => ['count' => 1], 'inc' => ['count' => 2]],
            $patch->serialize()
        );

        $this->assertSame($patch, $patch->reset());
        $this->assertEquals(['id' => 'abc123'], $patch->serialize());
    }

    public function testCanJsonEncodePatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->dec(['count' => 1]));
        $this->assertEquals(
            json_encode(['id' => 'abc123', 'dec' => ['count' => 1]]),
            json_encode($patch)
        );
    }

    public function testCanCreateAppendPatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->append('tags', ['foo', 'bar']));
        $this->assertEquals([
            'id' => 'abc123',
            'insert' => [
                'after' => 'tags[-1]',
                'items' => ['foo', 'bar']
            ]
        ], $patch->serialize());
    }

    public function testCanCreatePrependPatch()
    {
        $patch = new Patch('abc123');
        $this->assertSame($patch, $patch->prepend('tags', ['foo', 'bar']));
        $this->assertEquals([
            'id' => 'abc123',
            'insert' => [
                'before' => 'tags[0]',
                'items' => ['foo', 'bar']
            ]
        ], $patch->serialize());
    }

    /**
     * @expectedException Sanity\Exception\InvalidArgumentException
     * @expectedExceptionMessage "at"-argument which is one of
     */
    public function testThrowsWhenCallingInsertWithInvalidAtArgument()
    {
        $patch = new Patch('abc123');
        $patch->insert('foo', 'tags[-1]', ['foo', 'bar']);
    }

    /**
     * @expectedException Sanity\Exception\InvalidArgumentException
     * @expectedExceptionMessage "selector"-argument which must be a string
     */
    public function testThrowsWhenCallingInsertWithInvalidSelectorArgument()
    {
        $patch = new Patch('abc123');
        $patch->insert('before', ['tags' => -1], ['foo', 'bar']);
    }

    /**
     * @expectedException Sanity\Exception\InvalidArgumentException
     * @expectedExceptionMessage "items"-argument which must be an array
     */
    public function testThrowsWhenCallingInsertWithInvalidItemsArgument()
    {
        $patch = new Patch('abc123');
        $patch->insert('before', 'tags[-1]', 'boing');
    }

    public function testCanCreateSplicePatches()
    {
        $patch = function () { return new Patch('abc123'); };
        $replaceFirst = $patch()->splice('tags', 0, 1, ['foo'])->serialize();
        $insertInMiddle = $patch()->splice('tags', 5, 0, ['foo'])->serialize();
        $deleteLast = $patch()->splice('tags', -1, 1)->serialize();
        $deleteAllFromIndex = $patch()->splice('tags', 3, -1)->serialize();
        $allFromIndexDefault = $patch()->splice('tags', 3)->serialize();
        $negativeDelete = $patch()->splice('tags', -2, -2, ['foo'])->serialize();

        $this->assertEquals($replaceFirst['insert'], ['replace' => 'tags[0:1]', 'items' => ['foo']]);
        $this->assertEquals($insertInMiddle['insert'], ['replace' => 'tags[5:5]', 'items' => ['foo']]);
        $this->assertEquals($deleteLast['insert'], ['replace' => 'tags[-2:]', 'items' => []]);
        $this->assertEquals($deleteAllFromIndex['insert'], ['replace' => 'tags[3:-1]', 'items' => []]);
        $this->assertEquals($allFromIndexDefault['insert'], ['replace' => 'tags[3:-1]', 'items' => []]);
        $this->assertEquals($negativeDelete, $patch()->splice('tags', -2, 0, ['foo'])->serialize());
    }
}
