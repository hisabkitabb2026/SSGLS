package db

import (
	"fmt"

	"github.com/invoiceshelf/product-service/config"
	"github.com/invoiceshelf/product-service/internal/models"
	"gorm.io/driver/postgres"
	"gorm.io/gorm"
)

func New(cfg *config.Config) (*gorm.DB, error) {
	db, err := gorm.Open(postgres.Open(cfg.GetDSN()), &gorm.Config{})
	if err != nil {
		return nil, fmt.Errorf("failed to connect to database: %w", err)
	}

	// Configure connection pool
	sqlDB, err := db.DB()
	if err != nil {
		return nil, fmt.Errorf("failed to get database instance: %w", err)
	}

	sqlDB.SetMaxIdleConns(cfg.DBMaxIdleConns)
	sqlDB.SetMaxOpenConns(cfg.DBMaxOpenConns)
	sqlDB.SetConnMaxLifetime(cfg.DBConnMaxLifetime)

	return db, nil
}

func RunMigrations(db *gorm.DB) error {
	return db.AutoMigrate(
		&models.Product{},
	)
}
