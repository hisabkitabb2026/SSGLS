package config

import (
	"fmt"
	"os"
	"strconv"

	"github.com/joho/godotenv"
)

type Config struct {
	// Server
	Port        int
	Environment string
	Debug       bool

	// Database
	DatabaseURL string
	DBHost      string
	DBPort      int
	DBUser      string
	DBPassword  string
	DBName      string
	DBSSLMode   string

	// JWT
	JWTSecret string
	JWTExpiry int

	// RabbitMQ
	RabbitMQURL string
	RabbitMQHost string
	RabbitMQPort int
	RabbitMQUser string
	RabbitMQPass string

	// Logging
	LogLevel string

	// Metrics
	MetricsPort int
}

func LoadConfig() (*Config, error) {
	// Load .env file if it exists
	_ = godotenv.Load()

	cfg := &Config{
		Port:        getIntEnv("PORT", 8005),
		Environment: getEnv("ENVIRONMENT", "development"),
		Debug:       getBoolEnv("DEBUG", false),
		DBHost:      getEnv("DB_HOST", "localhost"),
		DBPort:      getIntEnv("DB_PORT", 5432),
		DBUser:      getEnv("DB_USER", "postgres"),
		DBPassword:  getEnv("DB_PASSWORD", "postgres"),
		DBName:      getEnv("DB_NAME", "settings_service"),
		DBSSLMode:   getEnv("DB_SSL_MODE", "disable"),
		JWTSecret:   getEnv("JWT_SECRET", "your-secret-key-change-in-production"),
		JWTExpiry:   getIntEnv("JWT_EXPIRY", 3600),
		RabbitMQHost: getEnv("RABBITMQ_HOST", "localhost"),
		RabbitMQPort: getIntEnv("RABBITMQ_PORT", 5672),
		RabbitMQUser: getEnv("RABBITMQ_USER", "guest"),
		RabbitMQPass: getEnv("RABBITMQ_PASS", "guest"),
		LogLevel:    getEnv("LOG_LEVEL", "info"),
		MetricsPort: getIntEnv("METRICS_PORT", 9090),
	}

	// Build database URL
	cfg.DatabaseURL = fmt.Sprintf("postgres://%s:%s@%s:%d/%s?sslmode=%s",
		cfg.DBUser,
		cfg.DBPassword,
		cfg.DBHost,
		cfg.DBPort,
		cfg.DBName,
		cfg.DBSSLMode,
	)

	// Build RabbitMQ URL
	cfg.RabbitMQURL = fmt.Sprintf("amqp://%s:%s@%s:%d/",
		cfg.RabbitMQUser,
		cfg.RabbitMQPass,
		cfg.RabbitMQHost,
		cfg.RabbitMQPort,
	)

	return cfg, nil
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}

func getIntEnv(key string, defaultValue int) int {
	if value := os.Getenv(key); value != "" {
		if intVal, err := strconv.Atoi(value); err == nil {
			return intVal
		}
	}
	return defaultValue
}

func getBoolEnv(key string, defaultValue bool) bool {
	if value := os.Getenv(key); value != "" {
		return value == "true" || value == "1" || value == "yes"
	}
	return defaultValue
}
