package main

import (
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"github.com/gorilla/mux"
	"github.com/invoiceshelf/product-service/config"
	"github.com/invoiceshelf/product-service/internal/db"
	"github.com/invoiceshelf/product-service/internal/events"
	"github.com/invoiceshelf/product-service/internal/handler"
	"github.com/invoiceshelf/product-service/internal/middleware"
	"github.com/invoiceshelf/product-service/internal/repository"
	"github.com/invoiceshelf/product-service/internal/service"
	"github.com/invoiceshelf/product-service/pkg/logger"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	amqp "github.com/rabbitmq/amqp091-go"
)

func main() {
	// Load configuration
	cfg, err := config.Load()
	if err != nil {
		fmt.Fprintf(os.Stderr, "Failed to load config: %v\n", err)
		os.Exit(1)
	}

	// Initialize logger
	log, err := logger.New(cfg.LogLevel)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Failed to initialize logger: %v\n", err)
		os.Exit(1)
	}
	defer log.Close()

	log.Info("Product Service Starting", "version", cfg.ServiceVersion)

	// Connect to database
	database, err := db.New(cfg)
	if err != nil {
		log.Fatal("Failed to connect to database", err)
	}

	// Run migrations
	if err := db.RunMigrations(database); err != nil {
		log.Fatal("Failed to run migrations", err)
	}

	// Connect to RabbitMQ
	rabbitmqConn, err := amqp.Dial(cfg.GetRabbitMQURL())
	if err != nil {
		log.Fatal("Failed to connect to RabbitMQ", err)
	}
	defer rabbitmqConn.Close()

	// Initialize event publisher
	publisher, err := events.NewPublisher(rabbitmqConn, log.Logger)
	if err != nil {
		log.Fatal("Failed to initialize event publisher", err)
	}
	defer publisher.Close()

	// Initialize repositories
	productRepo := repository.NewProductRepository(database)

	// Initialize services
	productService := service.NewProductService(productRepo, publisher, log.Logger)

	// Initialize handlers
	productHandler := handler.NewProductHandler(productService, log.Logger)
	healthHandler := handler.NewHealthHandler(database)

	// Setup routes
	router := mux.NewRouter()

	// Health check routes (no authentication)
	router.HandleFunc("/health", healthHandler.Health).Methods("GET")
	router.HandleFunc("/ready", healthHandler.Ready).Methods("GET")

	// Metrics route
	router.Handle("/metrics", promhttp.Handler())

	// API routes with JWT middleware
	apiRouter := router.PathPrefix("/api").Subrouter()
	apiRouter.Use(middleware.JWTMiddleware(cfg.JWTSecret, log))
	productHandler.Register(apiRouter)

	// Start metrics server
	go func() {
		metricsRouter := mux.NewRouter()
		metricsRouter.Handle("/metrics", promhttp.Handler())
		metricsRouter.HandleFunc("/health", healthHandler.Health).Methods("GET")
		metricsRouter.HandleFunc("/ready", healthHandler.Ready).Methods("GET")

		metricsAddr := fmt.Sprintf(":%d", cfg.MetricsPort)
		log.Info("Metrics server starting", "address", metricsAddr)
		if err := http.ListenAndServe(metricsAddr, metricsRouter); err != nil && err != http.ErrServerClosed {
			log.Error("Metrics server error", err)
		}
	}()

	// Create HTTP server
	addr := fmt.Sprintf(":%d", cfg.Port)
	server := &http.Server{
		Addr:    addr,
		Handler: router,
	}

	// Start server in a goroutine
	go func() {
		log.Info("HTTP server starting", "address", addr)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Error("HTTP server error", err)
		}
	}()

	// Wait for interrupt signal
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, os.Interrupt, syscall.SIGTERM)
	<-sigChan

	log.Info("Shutting down")
	if err := server.Close(); err != nil {
		log.Error("Error closing server", err)
	}
}
