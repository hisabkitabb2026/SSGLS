package config

import (
	"fmt"
	"os"
	"strconv"
	"time"

	"github.com/joho/godotenv"
)

type Config struct {
	// Server
	Port    int
	AppEnv  string
	LogLevel string

	// Database
	DBHost            string
	DBPort            int
	DBUser            string
	DBPassword        string
	DBName            string
	DBSSLMode         string
	DBMaxIdleConns    int
	DBMaxOpenConns    int
	DBConnMaxLifetime time.Duration

	// JWT
	JWTSecret      string
	JWTExpiration  int64

	// RabbitMQ
	RabbitMQHost     string
	RabbitMQPort     int
	RabbitMQUser     string
	RabbitMQPassword string
	RabbitMQVHost    string

	// Metrics
	MetricsPort int

	// Service
	ServiceName    string
	ServiceVersion string
}

func Load() (*Config, error) {
	// Load .env file if it exists
	_ = godotenv.Load()

	cfg := &Config{
		Port:              getEnvInt("PORT", 8003),
		AppEnv:            getEnv("APP_ENV", "development"),
		LogLevel:          getEnv("LOG_LEVEL", "info"),
		DBHost:            getEnv("DB_HOST", "localhost"),
		DBPort:            getEnvInt("DB_PORT", 5432),
		DBUser:            getEnv("DB_USER", "product_service"),
		DBPassword:        getEnv("DB_PASSWORD", "product_password"),
		DBName:            getEnv("DB_NAME", "product_service"),
		DBSSLMode:         getEnv("DB_SSL_MODE", "disable"),
		DBMaxIdleConns:    getEnvInt("DB_MAX_IDLE_CONNS", 10),
		DBMaxOpenConns:    getEnvInt("DB_MAX_OPEN_CONNS", 100),
		DBConnMaxLifetime: time.Duration(getEnvInt("DB_CONN_MAX_LIFETIME", 3600)) * time.Second,
		JWTSecret:         getEnv("JWT_SECRET", "your-super-secret-jwt-key-change-in-production"),
		JWTExpiration:     int64(getEnvInt("JWT_EXPIRATION", 86400)),
		RabbitMQHost:      getEnv("RABBITMQ_HOST", "localhost"),
		RabbitMQPort:      getEnvInt("RABBITMQ_PORT", 5672),
		RabbitMQUser:      getEnv("RABBITMQ_USER", "guest"),
		RabbitMQPassword:  getEnv("RABBITMQ_PASSWORD", "guest"),
		RabbitMQVHost:     getEnv("RABBITMQ_VHOST", "/"),
		MetricsPort:       getEnvInt("METRICS_PORT", 9090),
		ServiceName:       getEnv("SERVICE_NAME", "product-service"),
		ServiceVersion:    getEnv("SERVICE_VERSION", "1.0.0"),
	}

	return cfg, cfg.Validate()
}

func (c *Config) Validate() error {
	if c.Port <= 0 {
		return fmt.Errorf("invalid PORT: %d", c.Port)
	}
	if c.DBHost == "" {
		return fmt.Errorf("DB_HOST is required")
	}
	if c.DBUser == "" {
		return fmt.Errorf("DB_USER is required")
	}
	if c.DBName == "" {
		return fmt.Errorf("DB_NAME is required")
	}
	if c.JWTSecret == "" {
		return fmt.Errorf("JWT_SECRET is required")
	}
	return nil
}

func (c *Config) GetDSN() string {
	return fmt.Sprintf(
		"host=%s port=%d user=%s password=%s dbname=%s sslmode=%s",
		c.DBHost,
		c.DBPort,
		c.DBUser,
		c.DBPassword,
		c.DBName,
		c.DBSSLMode,
	)
}

func (c *Config) GetRabbitMQURL() string {
	return fmt.Sprintf(
		"amqp://%s:%s@%s:%d%s",
		c.RabbitMQUser,
		c.RabbitMQPassword,
		c.RabbitMQHost,
		c.RabbitMQPort,
		c.RabbitMQVHost,
	)
}

func (c *Config) IsDevelopment() bool {
	return c.AppEnv == "development"
}

func getEnv(key, defaultVal string) string {
	if value, exists := os.LookupEnv(key); exists {
		return value
	}
	return defaultVal
}

func getEnvInt(key string, defaultVal int) int {
	if value, exists := os.LookupEnv(key); exists {
		if intVal, err := strconv.Atoi(value); err == nil {
			return intVal
		}
	}
	return defaultVal
}
