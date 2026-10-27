const labels = {
    available: 'Available',
    in_use: 'In use',
    in_repair: 'In repair',
    retired: 'Retired',
    disposed: 'Disposed',
    lost_stolen: 'Lost / stolen',
};

export function formatAssetStatus(status) {
    return labels[status] ?? status;
}
