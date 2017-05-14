<?php
namespace SanityTest;

use Sanity\Patch;
use Sanity\Selection;
use Sanity\Transaction;

class TransactionTest extends TestCase
{
    public function testCanConstructNewTransactionWithUninitializedSelection()
    {
        $this->assertInstanceOf(Transaction::class, new Transaction());
    }

    public function testCanConstructNewTransactionWithInitializedSelection()
    {
        $selection = new Selection('abc123');
        $this->assertInstanceOf(Transaction::class, new Transaction($selection));
    }

    public function testCanConstructNewTransactionWithInitialOperations()
    {
        $transaction = new Transaction(['create' => ['_type' => 'post', 'title' => 'Foo']]);
        $this->assertEquals(['create' => ['_type' => 'post', 'title' => 'Foo']], $transaction->serialize());
    }

    /**
     * @expectedException Sanity\Exception\ConfigException
     * @expectedExceptionMessage `mutate()` method
     */
    public function testThrowsWhenCallingCommitWithoutClientContext()
    {
        $transaction = new Transaction();
        $transaction->create(['_type' => 'post']);
        $transaction->commit();
    }

    public function testCanAddCreateMutation()
    {
        $transaction = new Transaction();
        $this->assertSame($transaction, $transaction->create(['_type' => 'post']));
        $this->assertEquals(['create' => ['_type' => 'post']], $transaction->serialize()[0]);
    }

    public function testCanAddCreateIfNotExistsMutation()
    {
        $transaction = new Transaction();
        $doc = ['_id' => 'someId', '_type' => 'post'];
        $this->assertSame($transaction, $transaction->createIfNotExists($doc));
        $this->assertEquals(['createIfNotExists' => $doc], $transaction->serialize()[0]);
    }

    public function testCanAddCreateOrReplaceMutation()
    {
        $transaction = new Transaction();
        $doc = ['_id' => 'someId', '_type' => 'post'];
        $this->assertSame($transaction, $transaction->createOrReplace($doc));
        $this->assertEquals(['createOrReplace' => $doc], $transaction->serialize()[0]);
    }

    public function testCanAddDeleteMutation()
    {
        $transaction = new Transaction();
        $this->assertSame($transaction, $transaction->delete('abc123'));
        $this->assertEquals(['delete' => ['id' => 'abc123']], $transaction->serialize()[0]);
    }

    public function testCanAddDeleteMutationWithSelection()
    {
        $selection = new Selection('abc123');
        $transaction = new Transaction();
        $this->assertSame($transaction, $transaction->delete($selection));
        $this->assertEquals(['delete' => ['id' => 'abc123']], $transaction->serialize()[0]);
    }

    public function testCanAddPatchMutationWithOperationsArray()
    {
        $transaction = new Transaction();
        $this->assertSame($transaction, $transaction->patch('abc123', ['inc' => ['count' => 1]]));
        $this->assertEquals(['patch' => ['id' => 'abc123', 'inc' => ['count' => 1]]], $transaction->serialize()[0]);
    }

    public function testCanAddPatchMutationWithPatchInstance()
    {
        $patch = new Patch('abc123', ['dec' => ['count' => 1]]);
        $transaction = new Transaction();
        $this->assertSame($transaction, $transaction->patch($patch));
        $this->assertEquals(['patch' => ['id' => 'abc123', 'dec' => ['count' => 1]]], $transaction->serialize()[0]);
    }

    /**
     * @expectedException Sanity\Exception\InvalidArgumentException
     * @expectedExceptionMessage instantiated patch or an array
     */
    public function testThrowsWhenCallingPatchWithInvalidArgs()
    {
        $transaction = new Transaction();
        $this->assertSame($transaction, $transaction->patch('abc123'));
    }

    public function testCanResetPatch()
    {
        $transaction = new Transaction();
        $this->assertSame($transaction, $transaction->create(['_type' => 'post']));
        $this->assertEquals(
            [['create' => ['_type' => 'post']]],
            $transaction->serialize()
        );

        $this->assertSame($transaction, $transaction->reset());
        $this->assertEquals([], $transaction->serialize());
    }

    public function testCanJsonEncodePatch()
    {
        $transaction = new Transaction();
        $this->assertSame($transaction, $transaction->create(['_type' => 'post']));
        $this->assertEquals(
            json_encode([['create' => ['_type' => 'post']]]),
            json_encode($transaction)
        );
    }
}
