class RangeItems extends BadgeItems {
    constructor(items, func = null) {
        super(items, 'range', (func !== null) ? function (item) {
            func(item, 'range');
        } : null);
    }
    getName(item) {
        return item.zone + (item.column !== null ? ' - ' + item.column : '');
    }
}

class LocationItems extends BadgeItems {
    constructor(items, func = null) {
        super(items, 'location', (func !== null) ? function (item) {
            func(item, 'location');
        } : null);
    }
    getState(item) {
        return item.state;
    }
}

class BookLocations {
    constructor(ranges, locations) {
        this.ranges = ranges;
        this.locations = locations;
    }
    addEvents() {
        this.ranges.addEvents();
        this.locations.addEvents();
        return this;
    }
    reFill() {
        this.ranges.reFill();
        this.locations.reFill();
    }
    removeRange(item) {
        this.ranges.removeItem(item);
    }
    removeLocation(item) {
        this.locations.removeItem(item);
    }
    refresh(ranges, locations) {
        this.ranges.setItems(ranges);
        this.locations.setItems(locations);
        this.reFill();
    }
}
