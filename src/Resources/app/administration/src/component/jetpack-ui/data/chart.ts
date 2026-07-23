import template from './chart.html.twig';
import type { PropType } from 'vue';
import type { JetpackChartPoint } from '../types';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        points: {
            type: Array as PropType<JetpackChartPoint[]>,
            required: true,
        },
        type: {
            type: String as PropType<'bar' | 'line'>,
            required: false,
            default: 'bar',
        },
        title: { type: String, required: false, default: '' },
        ariaLabel: { type: String, required: false, default: '' },
    },

    computed: {
        maximum(): number {
            return Math.max(1, ...this.points.map((point) => point.value));
        },

        barWidth(): number {
            return Math.max(12, Math.min(64, 440 / Math.max(1, this.points.length)));
        },

        linePoints(): string {
            return this.points.map((point, index) => `${this.pointX(index)},${this.pointY(point.value)}`).join(' ');
        },
    },

    methods: {
        pointX(index: number): number {
            if (this.points.length <= 1) {
                return 310;
            }

            return 60 + (index * 500) / (this.points.length - 1);
        },

        pointY(value: number): number {
            return 220 - (Math.max(0, value) / this.maximum) * 190;
        },

        barX(index: number): number {
            const slotWidth = 520 / Math.max(1, this.points.length);

            return 50 + index * slotWidth + (slotWidth - this.barWidth) / 2;
        },
    },
});
