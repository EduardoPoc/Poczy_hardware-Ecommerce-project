import test from 'node:test';
import assert from 'node:assert/strict';
import { ApiError, isSessionFailure, isUncertainFailure } from '../src/shared/api/errors.js';

test('expired sessions are distinguished from permission and validation failures', () => {
  for (const status of [401, 419])
    assert.equal(isSessionFailure(new ApiError('Session', status)), true);
  for (const status of [0, 403, 409, 422, 500])
    assert.equal(isSessionFailure(new ApiError('Other', status)), false);
});

test('lost responses and server failures require checking the result before writing again', () => {
  for (const failure of [
    new Error('Connection'),
    new ApiError('Lost'),
    new ApiError('Server', 500),
    new ApiError('Unavailable', 503),
  ]) {
    assert.equal(isUncertainFailure(failure), true);
  }
  for (const status of [401, 403, 409, 419, 422, 429]) {
    assert.equal(isUncertainFailure(new ApiError('Rejected', status)), false);
  }
});
