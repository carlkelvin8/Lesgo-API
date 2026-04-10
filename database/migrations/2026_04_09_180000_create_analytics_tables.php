<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Analytics events for tracking user actions
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type'); // order_created, order_completed, driver_online, etc.
            $table->string('event_category'); // order, driver, customer, payment, etc.
            $table->string('event_action'); // create, update, delete, view, etc.
            $table->string('event_label')->nullable();
            $table->decimal('event_value', 15, 2)->nullable(); // monetary value
            $table->json('properties')->nullable(); // additional event data
            $table->string('session_id')->nullable();
            $table->string('device_type')->nullable(); // mobile, web, api
            $table->string('platform')->nullable(); // ios, android, web
            $table->string('app_version')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('event_time');
            $table->timestamps();

            $table->index(['event_type', 'event_time']);
            $table->index(['user_id', 'event_time']);
            $table->index(['event_category', 'event_time']);
            $table->index(['event_time']);
        });

        // Daily aggregated metrics
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('metric_type'); // revenue, orders, drivers, customers
            $table->string('metric_category')->nullable(); // service_type, region, etc.
            $table->string('metric_key'); // total_revenue, completed_orders, active_drivers
            $table->decimal('metric_value', 15, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['date', 'metric_type', 'metric_category', 'metric_key']);
            $table->index(['date', 'metric_type']);
            $table->index(['metric_type', 'metric_key']);
        });

        // Driver performance metrics
        Schema::create('driver_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->integer('total_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->integer('cancelled_orders')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_distance_km', 10, 2)->default(0);
            $table->integer('online_minutes')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->integer('total_ratings')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(0); // percentage
            $table->decimal('completion_rate', 5, 2)->default(0); // percentage
            $table->decimal('average_delivery_time', 8, 2)->nullable(); // minutes
            $table->integer('customer_complaints')->default(0);
            $table->json('performance_data')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'date']);
            $table->index(['date', 'completed_orders']);
            $table->index(['date', 'total_revenue']);
        });

        // Customer behavior metrics
        Schema::create('customer_behavior_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->integer('total_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->integer('cancelled_orders')->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->integer('app_sessions')->default(0);
            $table->integer('session_duration_minutes')->default(0);
            $table->json('preferred_services')->nullable(); // array of service IDs
            $table->json('preferred_times')->nullable(); // peak usage hours
            $table->json('preferred_locations')->nullable(); // frequently used addresses
            $table->decimal('customer_lifetime_value', 15, 2)->default(0);
            $table->integer('referrals_made')->default(0);
            $table->decimal('churn_probability', 5, 4)->nullable(); // 0-1 probability
            $table->json('behavior_data')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'date']);
            $table->index(['date', 'total_spent']);
            $table->index(['date', 'churn_probability']);
        });

        // Service demand patterns
        Schema::create('service_demand_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('hour_of_day'); // 0-23
            $table->integer('day_of_week'); // 1-7 (Monday-Sunday)
            $table->integer('total_requests')->default(0);
            $table->integer('completed_requests')->default(0);
            $table->integer('cancelled_requests')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('average_wait_time', 8, 2)->nullable(); // minutes
            $table->decimal('average_completion_time', 8, 2)->nullable(); // minutes
            $table->integer('peak_demand_score')->default(0); // 1-100
            $table->decimal('supply_demand_ratio', 5, 2)->nullable();
            $table->json('demand_data')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'date', 'hour_of_day']);
            $table->index(['date', 'service_id']);
            $table->index(['service_id', 'peak_demand_score']);
        });

        // Geofence effectiveness metrics
        Schema::create('geofence_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geofence_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('total_entries')->default(0);
            $table->integer('total_exits')->default(0);
            $table->integer('total_dwells')->default(0);
            $table->integer('unique_users')->default(0);
            $table->decimal('average_dwell_time', 8, 2)->nullable(); // minutes
            $table->integer('orders_triggered')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0); // percentage
            $table->decimal('revenue_generated', 15, 2)->default(0);
            $table->integer('notifications_sent')->default(0);
            $table->integer('notification_clicks')->default(0);
            $table->decimal('notification_ctr', 5, 2)->default(0); // click-through rate
            $table->json('effectiveness_data')->nullable();
            $table->timestamps();

            $table->unique(['geofence_id', 'date']);
            $table->index(['date', 'conversion_rate']);
            $table->index(['geofence_id', 'revenue_generated']);
        });

        // Revenue analytics
        Schema::create('revenue_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('revenue_type'); // gross, net, commission, driver_earnings
            $table->string('revenue_source'); // orders, subscriptions, fees
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('partner_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('PHP');
            $table->integer('transaction_count')->default(1);
            $table->decimal('average_transaction_value', 10, 2)->nullable();
            $table->json('breakdown')->nullable(); // detailed revenue breakdown
            $table->timestamps();

            $table->index(['date', 'revenue_type']);
            $table->index(['date', 'service_id']);
            $table->index(['revenue_type', 'amount']);
        });

        // Predictive analytics models
        Schema::create('predictive_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_name'); // demand_forecast, churn_prediction, revenue_forecast
            $table->string('model_type'); // regression, classification, time_series
            $table->string('target_variable'); // demand, churn, revenue
            $table->json('features'); // input features used
            $table->json('hyperparameters')->nullable();
            $table->decimal('accuracy_score', 5, 4)->nullable();
            $table->decimal('precision_score', 5, 4)->nullable();
            $table->decimal('recall_score', 5, 4)->nullable();
            $table->decimal('f1_score', 5, 4)->nullable();
            $table->json('model_metrics')->nullable();
            $table->timestamp('trained_at');
            $table->timestamp('last_prediction_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('model_data')->nullable(); // serialized model or parameters
            $table->timestamps();

            $table->index(['model_name', 'is_active']);
            $table->index(['trained_at']);
        });

        // Predictions and forecasts
        Schema::create('analytics_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('predictive_models')->onDelete('cascade');
            $table->string('prediction_type'); // demand, churn, revenue
            $table->string('prediction_target'); // user_id, service_id, date, etc.
            $table->string('prediction_period'); // hourly, daily, weekly, monthly
            $table->timestamp('prediction_date');
            $table->decimal('predicted_value', 15, 4);
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->decimal('actual_value', 15, 4)->nullable(); // filled when actual data is available
            $table->decimal('prediction_error', 15, 4)->nullable();
            $table->json('prediction_data')->nullable();
            $table->timestamps();

            $table->index(['model_id', 'prediction_date']);
            $table->index(['prediction_type', 'prediction_date']);
            $table->index(['prediction_target', 'prediction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_predictions');
        Schema::dropIfExists('predictive_models');
        Schema::dropIfExists('revenue_analytics');
        Schema::dropIfExists('geofence_analytics');
        Schema::dropIfExists('service_demand_metrics');
        Schema::dropIfExists('customer_behavior_metrics');
        Schema::dropIfExists('driver_performance_metrics');
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('analytics_events');
    }
};