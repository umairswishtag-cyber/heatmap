import { record } from 'rrweb';
import { recorderPrivacyOptions } from './privacy.js';

export function startRecorder(emit, privacy) {
  return record({
    emit: (event) => emit('rrweb', event),
    checkoutEveryNms: 60_000,
    sampling: { mousemove: 50, scroll: 150, input: 'last' },
    ...recorderPrivacyOptions(privacy),
  });
}
