<?php

// Example test case
test('example test', function () {
    expect(true)->toBeTrue();
});

// Test for a function
test('addition works correctly', function () {
    $result = 1 + 1;
    expect($result)->toBe(2);
});