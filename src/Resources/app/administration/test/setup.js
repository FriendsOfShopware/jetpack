const { defineComponent } = require('vue');

class Criteria {
    constructor(page = 1, limit = 25) {
        this.page = page;
        this.limit = limit;
        this.term = '';
        this.associations = [];
        this.sortings = [];
    }

    static fromCriteria(criteria) {
        const clone = new Criteria(criteria.page, criteria.limit);
        Object.assign(clone, criteria);
        clone.associations = [...(criteria.associations || [])];
        clone.sortings = [...(criteria.sortings || [])];

        return clone;
    }

    static sort(property, direction = 'ASC', naturalSorting = false) {
        return { field: property, order: direction, naturalSorting };
    }

    setPage(page) {
        this.page = page;
        return this;
    }

    setLimit(limit) {
        this.limit = limit;
        return this;
    }

    setTerm(term) {
        this.term = term;
        return this;
    }

    addAssociation(association) {
        this.associations.push(association);
        return this;
    }

    addSorting(sorting) {
        this.sortings.push(sorting);
        return this;
    }
}

class EntityCollection extends Array {
    getIds() {
        return this.map((entity) => entity.id);
    }

    add(entity) {
        this.push(entity);
    }

    remove(id) {
        const index = this.findIndex((entity) => entity.id === id);
        if (index < 0) {
            return false;
        }
        this.splice(index, 1);
        return true;
    }
}

const privileges = {
    addPrivilegeMappingEntry: jest.fn(),
};

global.Shopware = {
    Component: {
        register: jest.fn((_name, config) => (typeof config === 'object' ? defineComponent(config) : config)),
        wrapComponentConfig: defineComponent,
    },
    Context: {
        api: {},
    },
    Application: {
        start: jest.fn(),
    },
    Data: {
        Criteria,
        EntityCollection,
    },
    Module: {
        register: jest.fn(),
    },
    Mixin: {
        getByName: jest.fn(() => ({
            props: {
                element: {
                    type: Object,
                    required: true,
                },
                disabled: {
                    type: Boolean,
                    default: false,
                },
            },
            methods: {
                initElementConfig() {},
                initElementData() {},
            },
        })),
    },
    Service: jest.fn(() => privileges),
};

global.ResizeObserver = class ResizeObserver {
    observe() {}

    unobserve() {}

    disconnect() {}
};
