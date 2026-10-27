import { describe, expect, it } from 'vitest';
import { formatAssetStatus } from './assetStatus.js';

describe('formatAssetStatus', () => {
    it('formats known lifecycle statuses for display', () => {
        expect(formatAssetStatus('in_repair')).toBe('In repair');
        expect(formatAssetStatus('lost_stolen')).toBe('Lost / stolen');
    });

    it('keeps future status values visible instead of hiding them', () => {
        expect(formatAssetStatus('awaiting_review')).toBe('awaiting_review');
    });
});
